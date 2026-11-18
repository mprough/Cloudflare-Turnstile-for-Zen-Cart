<?php

use ProWebs\EdgeChallenge\Cloudflare;
use ProWebs\EdgeChallenge\Targets;

require 'includes/application_top.php';
require __DIR__ . '/includes/prowebs_edge/Targets.php';
require __DIR__ . '/includes/prowebs_edge/Cloudflare.php';

$edgeConfigPath = __DIR__ . '/includes/prowebs_edge/config.local.php';
$edgeConfig = is_file($edgeConfigPath) ? require $edgeConfigPath : [];
$edgeHost = $edgeConfig['host'] ?? '';
if ($edgeHost === '') {
    $edgeHost = (string) parse_url(HTTPS_SERVER ?: HTTP_SERVER, PHP_URL_HOST);
}
$edgeCatalog = DIR_WS_CATALOG;
$edgeAdmin = DIR_WS_ADMIN;
$edgePrefix = Targets::prefix($edgeHost, $edgeCatalog);
$edgeConfigured = false;
$edgeError = '';
$edgeRules = [];
$edgeApi = null;
$edgeFlash = $_SESSION['prowebsEdgeFlash'] ?? null;
unset($_SESSION['prowebsEdgeFlash']);

try {
    $edgeApi = new Cloudflare(
        $edgeConfig['zone'] ?? (getenv('PROWEBS_CF_ZONE_ID') ?: ''),
        $edgeConfig['token'] ?? (getenv('PROWEBS_CF_API_TOKEN') ?: '')
    );
    $edgeConfigured = true;
} catch (InvalidArgumentException | RuntimeException $exception) {
    $edgeError = $exception->getMessage();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $edgeToken = $_POST['securityToken'] ?? null;
    $edgeSessionToken = $_SESSION['securityToken'] ?? null;
    if (!is_string($edgeToken) || !is_string($edgeSessionToken)
        || $edgeSessionToken === '' || !hash_equals($edgeSessionToken, $edgeToken)
    ) {
        http_response_code(403);
        exit('Invalid form token. Reload the Admin page and try again.');
    }

    try {
        if ($edgeApi === null) {
            throw new RuntimeException($edgeError);
        }
        $edgeAction = $_POST['operation'] ?? '';
        if ($edgeAction === 'save') {
            $edgeType = $_POST['target_type'] ?? '';
            $edgeTarget = $_POST['target'] ?? '';
            $edgeMinutes = $_POST['duration'] ?? '0';
            if (!is_string($edgeType) || !is_string($edgeTarget) || !is_string($edgeMinutes)
                || !in_array($edgeMinutes, ['0', '15', '30', '60', '120'], true)
            ) {
                throw new InvalidArgumentException('Check the target and duration.');
            }
            $edgeApi->save(Targets::rule(
                $edgeHost, $edgeCatalog, $edgeAdmin, $edgeType, trim($edgeTarget), (int) $edgeMinutes
            ));
            $_SESSION['prowebsEdgeFlash'] = ['success', 'Cloudflare saved the Managed Challenge.'];
        } elseif ($edgeAction === 'remove' && is_string($_POST['rule_id'] ?? null)) {
            $edgeApi->remove($_POST['rule_id'], $edgePrefix);
            $_SESSION['prowebsEdgeFlash'] = ['success', 'Cloudflare removed the challenge rule.'];
        } else {
            throw new InvalidArgumentException('Choose a valid rule action.');
        }
    } catch (InvalidArgumentException | RuntimeException $exception) {
        $_SESSION['prowebsEdgeFlash'] = ['danger', $exception->getMessage()];
    }
    zen_redirect(zen_href_link(FILENAME_PROWEBS_EDGE_CHALLENGE));
}

if ($edgeApi !== null) {
    try {
        $edgeRules = $edgeApi->rules($edgePrefix);
    } catch (RuntimeException $exception) {
        $edgeError = $exception->getMessage();
    }
}

$edgeEscape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$edgeDurationOptions = [0 => 'Until removed', 15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', 120 => '2 hours'];
?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
<?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
</head>
<body>
<?php require DIR_WS_INCLUDES . 'header.php'; ?>
<main class="container-fluid">
    <h1>Cloudflare page challenges</h1>
    <p>Challenge visits to selected storefront pages on <?php echo $edgeEscape($edgeHost); ?>.</p>
    <?php if ($edgeFlash !== null) { ?>
        <div role="status" class="alert alert-<?php echo $edgeEscape($edgeFlash[0]); ?>"><?php echo $edgeEscape($edgeFlash[1]); ?></div>
    <?php } ?>
    <?php if ($edgeError !== '') { ?>
        <div role="alert" class="alert alert-warning"><?php echo $edgeEscape($edgeError); ?></div>
    <?php } ?>
    <?php if ($edgeConfigured && $edgeError === '') { ?>
        <h2>Add a challenge</h2>
        <?php echo zen_draw_form('edgePreset', FILENAME_PROWEBS_EDGE_CHALLENGE); ?>
            <input type="hidden" name="operation" value="save">
            <input type="hidden" name="target_type" value="route">
            <label for="edgePreset">Storefront page</label>
            <select id="edgePreset" name="target" required>
                <option value="">Select a page</option>
                <?php foreach (Targets::PRESETS as $edgePage => $edgeLabel) { ?>
                    <option value="<?php echo $edgeEscape($edgePage); ?>"><?php echo $edgeEscape($edgeLabel); ?></option>
                <?php } ?>
            </select>
            <button type="submit" class="btn btn-primary">Enable Managed Challenge</button>
        </form>
        <h3>Product or custom page</h3>
        <?php echo zen_draw_form('edgeCustom', FILENAME_PROWEBS_EDGE_CHALLENGE); ?>
            <input type="hidden" name="operation" value="save">
            <label for="edgeType">Target type</label>
            <select id="edgeType" name="target_type">
                <option value="product">Product ID</option>
                <option value="route">Zen Cart page name</option>
                <option value="path">Exact public URL path</option>
            </select>
            <label for="edgeTarget">Target</label>
            <input id="edgeTarget" name="target" maxlength="151" required>
            <label for="edgeDuration">Duration</label>
            <select id="edgeDuration" name="duration">
                <?php foreach ($edgeDurationOptions as $edgeMinutes => $edgeLabel) { ?>
                    <option value="<?php echo $edgeMinutes; ?>"><?php echo $edgeEscape($edgeLabel); ?></option>
                <?php } ?>
            </select>
            <button type="submit" class="btn btn-primary">Enable Managed Challenge</button>
        </form>
        <h2>Storefront challenge rules</h2>
        <?php if ($edgeRules === []) { ?>
            <p>No challenge rules created by this plugin.</p>
        <?php } else { ?>
            <table class="table">
                <thead><tr><th scope="col">Target</th><th scope="col">Expires (UTC)</th><th scope="col">Action</th></tr></thead>
                <tbody>
                <?php foreach ($edgeRules as $edgeRule) { ?>
                    <tr>
                        <td>
                            <?php echo $edgeEscape($edgeRule['description'] ?? 'Managed Challenge'); ?>
                            <?php if (empty($edgeRule['enabled'])) { ?> (disabled)<?php } ?>
                        </td>
                        <td><?php
                            echo preg_match('/http\.request\.timestamp\.sec lt ([0-9]{10})\)/', $edgeRule['expression'] ?? '', $edgeExpiry)
                                ? gmdate('Y-m-d H:i', (int) $edgeExpiry[1])
                                : 'Until removed';
                        ?></td>
                        <td>
                            <?php echo zen_draw_form('edgeRemove_' . $edgeRule['id'], FILENAME_PROWEBS_EDGE_CHALLENGE); ?>
                                <input type="hidden" name="operation" value="remove">
                                <input type="hidden" name="rule_id" value="<?php echo $edgeEscape($edgeRule['id']); ?>">
                                <button type="submit" class="btn btn-default">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    <?php } ?>
</main>
<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require DIR_WS_INCLUDES . 'application_bottom.php'; ?>
