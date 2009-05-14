<?php

/**
 * Interpreta o arquivo identificado em $sArqDados e devolve uma matriz 
 * contendo os dados interpretados
 * 
 * @return 
 * Array(
 *  [BANCO] => 1
 *  [BANCO_LITERAL] => NOME DO BANCO
 *  [AGENCIA] => 1234
 *  [NOME_CEDENTE] => NOME DO CEDENTE
 *  [CODIGO_CEDENTE] => 850
 *  [CODIGO_CEDENTE_DV] => 8
 *  [CONTA_CORRENTE] => 345
 *  [CONTA_CORRENTE_DV] => 0
 *  [DTMOVIMENTO] => 10/03/2009
 *  [DETALHE] => Array(
 *   [0] => Array(
 *    [AGENCIA_PARAMENTO] => 2400
 *    [AGENCIA_PARAMENTO_DV] => 0
 *    [BANCO_PARAMENTO] => 399
 *    [CARTEIRA] => 02
 *    [CODIGO_DE_BAIXA_RECUSA] => 00
 *    [CONTA_CORRENTE] => 345
 *    [CONTA_CORRENTE_DV] => 0
 *    [CONVENIO] => 0
 *    [DTCREDITO] => 09/03/2009
 *    [DTPAGAMENTO] => 09/03/2009
 *    [DTVENCIMENTO] => 07/03/2009
 *    [ESPECIE_TITULO] => 99
 *    [NOSSO_NUMERO] => 2034050
 *    [NOSSO_NUMERO_DV] => 1
 *    [TIPO_REGISTRO] => 1
 *    [VALOR_DOCUMENTO] => 369.06
 *    [VALOR_LANCAMENTO] => 0000000000160
 *    [VALOR_PAGAMENTO] => 369.06
 *    [VALOR_TARIFA] => 1.6
 *   )
 * ...
 *  ) 
 * );
 *
 */
function Retorno_CNAB400($sArqDados) {
	/*definiзгo dos arquivos layout*/
	$sLayoutHeader = './retorno_cnab400_header.txt';
	$sLayoutDetalhe = './retorno_cnab400_detalhe.txt';
	
	/*cria o objeto importador*/
	$kmi = new KM_importador($sLayoutHeader, $sArqDados);
	
	/**
	 * *******************
	 * Consistencia do arquivo
	 * *******************
	 */
	$aLinha = $kmi->getLine(0, true);
	/*consistencia do tamanho das linhas de todo o arquivo*/
	if (!$kmi->addCheck(KM_IMPORTADOR_CONSISTIR_TAMANHO)) {
		return $kmi->getError();
	}
	
	/*consistencia do Tipo do Registro  ("0" – Registro Header) */
	if ($aLinha['TIPO_REGISTRO'] != 0) {
		return sprintf('O campo "%s" deveria ser "%s", encontrado: "%s"', 'Tipo Registro', '0', 
						$aLinha['TIPO_REGISTRO']);
	}
	
	/*consistencia do tipo do arquivo*/
	if ($aLinha['TIPO_ARQUIVO_LITERAL'] != 'RETORNO') {
		return sprintf('O campo "%s" deveria ser "%s", encontrado: "%s"', 'Tipo do arquivo', 'RETORNO', 
						$aLinha['TIPO_ARQUIVO_LITERAL']);
	}
	
	/**
	 * *******************
	 * Interpreta os detalhes
	 * *******************
	 */
	/*irб guardar o resultado*/
	$aResultado = array();
	
	/*campos que serгo lidos do header*/
	$aLerHeader = array(
						'BANCO', 
						'BANCO_LITERAL', 
						'AGENCIA', 
						'NOME_CEDENTE', 
						'CODIGO_CEDENTE', 
						'CODIGO_CEDENTE_DV', 
						'CONTA_CORRENTE', 
						'CONTA_CORRENTE_DV', 
						'DTMOVIMENTO');
	foreach ($aLerHeader as $campo) {
		$aResultado[$campo] = $aLinha[$campo];
	}
	
	/*campos que serгo lidos do detalhe*/
	$aLerDetalhe = array(
						'CONTA_CORRENTE', 
						'CONTA_CORRENTE_DV', 
						'NOSSO_NUMERO', 
						'NUMERO_DOCUMENTO', 
						'DTVENCIMENTO', 
						'VALOR_DOCUMENTO', 
						'DTPAGAMENTO', 
						'VALOR_PAGAMENTO', 
						'BANCO_PARAMENTO', 
						'AGENCIA_PARAMENTO');
	
	$kmi->setLayout($sLayoutDetalhe, 1, $kmi->getNumLines() - 2);
	$aLerDetalhe = $kmi->getFields();
	/*consistencia do Tipo do Registro ("1" – Registro Detalhe)*/
	if (!$kmi->addCheck(KM_IMPORTADOR_CONSISTIR_FUNC, '[TIPO_REGISTRO] == "1"')) {
		$aLinha = $kmi->getLine($kmi->getErrorLine());
		return sprintf('O campo "%s" deveria ser "%s", encontrado: "%s"', 'Tipo Registro', '1', 
						$aLinha['TIPO_REGISTRO']);
	}
	
	/*Lк todos os */
	$aDados = array();
	$i = 0;
	while ($kmi->fetch()) {
		foreach ($aLerDetalhe as $campo)
			$aDados[$i][$campo] = $kmi->$campo;
		$i++;
	}
	$aResultado['DETALHE'] = $aDados;
	
	/*retorna o array de resultados*/
	return $aResultado;
}
?>