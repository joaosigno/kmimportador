<?php

/**
 * Arquivo que contem a classe de importaчуo, com funчѕes de consistencia
 * e de leitura de arquivos com base em layout pre-definido
 */
require_once '../km_importador.php';

/**
 * Arquivo que contem a funчуo para interpretaчуo
 * de arquivos CNAB400
 */
require_once 'retorno_cnab400.php';

/*identifica qual onde esta o arquivo de retorno*/
$sArquivoRetorno = './ARQ_RETORNO.TXT';

/*exibe a matriz*/
echo nl2br(print_r(Retorno_CNAB400($sArquivoRetorno), true));
?>