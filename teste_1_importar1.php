<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<title>Participantes da Reuni�o do dia 14/02</title>
<style>
<!--
table,td,th {
	border: 1px solid #000000;
	border-collapse: collapse;
}

th {
	background-color: #DADADA;
}
-->
</style>
</head>

<body>
<h1>Limples Listagem</h1>
<?php
/**
 * Teste 1 importador 1
 * 
 * @package   KM_importador
 * @author    Diego Tolentino <diegotolentino@gmail.com>
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPL
 * @link      http://code.google.com/p/kmimportador/
 * @version   1.0 UTF-8
 */

/**
 * classe km_importador
 */
require 'km_importador.php';

/*define o arquivo de layout*/
$sLayout = 'teste_1_layout.txt';

/*define o arquivo de dados*/
$sDados = 'teste_1_dados.txt';

/*cria o objeto KM_importador*/

$kmi = new KM_importador($sLayout, $sDados, KM_IMPORTADOR_FORMATO_FIXO, KM_IMPORTADOR_ORIGEM_ARQUIVO);

/*confere se todas as linahs tem o tamanho do layout*/
if (!$kmi->addCheck(KM_IMPORTADOR_CONSISTIR_TAMANHO)) {
	echo $kmi->getError() . '<br>';
}

/*come�o da escrita da tabela*/
echo '<table border="1">';
echo '  <tr>';
echo '    <th>Inscrito</th>';
echo '    <th>Compareceu</th>';
echo '    <th>Valor que gastou (R$)</th>';
echo '  </tr>';

while ($kmi->fetch()) {
	echo '</tr>';
	echo '    <td>' . $kmi->NMPESSOA . '</td>';
	echo '    <td>' . ($kmi->COMPARECEU == 'S' ? 'Sim' : '') . '</td>';
	echo '    <td>' . ($kmi->VALOR_GASTO ? number_format($kmi->VALOR_GASTO, 2) : '&nbsp;') . '</td>';
	echo '</tr>';
}
echo '</table>';
?>

</body>
</html>