<?php require_once(__DIR__ . '/../config.php'); ?>
<!DOCTYPE html>
<?php if (isset($_SESSION['MM_Usuario'])): ?>
<html lang="pt-BR">
<?php else: ?>
<html lang="pt-BR" class="has-navbar-fixed-top">
<?php endif; ?>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <meta name="hwb-csrf" content="<?= hwb_h($hwb_csrf) ?>">
    <title>MK - AUTH :: <?php echo isset($Manifest->{'name'}) ? $Manifest->{'name'} . " - V " . $Manifest->{'version'} : 'BRAS Huawei'; ?></title>

    <!-- Grid isolado: NAO usar bootstrap.min.css inteiro, o reset dele vaza para o topo.php -->
    <link href="css/vendor/grid-utilities.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/mk-auth.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/font-awesome.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/bi-icons.css" rel="stylesheet" type="text/css" />

    <!-- jQuery vem do core e SEMPRE antes do mk-auth.js -->
    <script src="../../scripts/jquery.js"></script>
    <script src="../../scripts/mk-auth.js"></script>

    <link href="css/hwb.css?v=<?= time() ?>" rel="stylesheet" type="text/css" />
    <script src="js/hwb-ui.js?v=<?= time() ?>"></script>
</head>
