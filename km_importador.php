<?php

/**
 * Arquivo que contem a classe de importa��o, com fun��es de consistencia
 * e de leitura de arquivos com base em layout pre-definido
 *
 * @author Diego Tolentino
 * @package CORE
 */

/**
 * Consiste o tamanho da linha (tamanho fixo)
 * 
 * Ex: 
 * //confere se todas as linhas tem o tamanho de 150 caracteres
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_TAMANHO, 150); 
 *
 */
define('KM_IMPORTADOR_CONSISTIR_TAMANHO', 1);

/**
 * Consiste o numero de colunas (dividido por tab)
 * 
 * Ex: 
 * //confere se todas as linhas tem 10 colunas
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_COLUNAS, 10);
 * 
 */
define('KM_IMPORTADOR_CONSISTIR_COLUNAS', 2);

/**
 * Consiste usando uma express�o regular passada como argumento
 * 
 * Ex: 
 * //confere o campo 'NM_CAMPO' usando a express�o regular '^[0-9]{8}$'
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_REGEX, 'NM_CAMPO', '^[0-9]{8}$');
 *
 */
define('KM_IMPORTADOR_CONSISTIR_REGEX', 3);

/**
 * Consiste utilizando um trecho que ser� interpretado pela fun��o eval
 * geralmente um teste de igualdade ou uma fun��o
 *
 * Ex: 
 * //confere se o campo 'VALOR1' � menor que 'VALOR2'
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_FUNC, '[VALOR1] < [VALOR2]');
 * 
 * //confere se o campo 'COD_TIPO' esta entre 1 e 5
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_FUNC, '[COD_TIPO] >=1 && [COD_TIPO] <=5');
 * 
 * //confere o campo 'COD_EMPRESA' usando a fun��o conferirEmpresa
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_FUNC, 'conferirEmpresa([COD_EMPRESA])');
 * 
 */
define('KM_IMPORTADOR_CONSISTIR_FUNC', 4);

/**
 * Os campos do arquivo possuem tamanho fixo
 *
 */
define('KM_IMPORTADOR_FORMATO_FIXO', 1);

/**
 * Os campos do arquivo s�o delimitados pelo caracter definido 
 * em KM_importador::sepCampo
 * 
 * @todo Fazer op��o para que o formato delimitado aceite tamanho maximo, pode ate usar
 * a mesma posi��o de tamanho do formato de tamanho fixo
 */
define('KM_IMPORTADOR_FORMATO_DELIMITADO', 2);

/**
 * A variavel $dados da instancia da classe � passada como um
 * endere�o de arquivo
 */
define('KM_IMPORTADOR_ORIGEM_ARQUIVO', 1);

/**
 * A variavel $dados da instancia da classe � passada como um
 * endere�o de arquivo
 */
define('KM_IMPORTADOR_ORIGEM_STRING', 2);

/**
 * classe para importa��o de arquivos
 *
 */
class KM_importador {

	/**
	 * Se � para parar a execu��o do php com um die() caso
	 * ocorra um erro
	 *
	 * @var boolean
	 */
	private $abortOnError = false;

	/**
	 * Guarda um array de dados para fazer fetch
	 *
	 * @var array
	 */
	private $arrayDados = array();

	/**
	 * Guarda o arquivo de layout lido do disco
	 * no formato 
	 * array('nome_do_campo'=>
	 *  //Caso o arquivo seja de tamanho fixo
	 *  Array('tam'=>int(tamanho), 'tipo'(�)=>String(tipo do campo), 'ini'=>int(inicio), 'fin'=>int(final)
	 *    OU
	 *  //Caso o arquivo seja separado por tab
	 *  Array('pos'=>int(posi��o do campo no arquivo))
	 * )
	 * 
	 * (�) As defini��es dos tipos segue o padr�o ditado no anexo 'DOC - DSV - KM_importador.doc'
	 * 
	 * @var array
	 */
	private $arrayLayout = array();

	/**
	 * Dados para importa��o
	 * 
	 * @var string
	 */
	private $dados = '';

	/**
	 * Linha onde aconteceu o erro, se estiver na parte
	 * de consistencia retornar� a linha do arquivo de dados,
	 * caso contrario retorna a linha do programa onde
	 * aconteceu o erro
	 *
	 * @var integer
	 */
	private $errorLine = '';

	/**
	 * Guarda a mensagem do erro ocorrido
	 *
	 * @var string
	 */
	private $errorMsg = '';

	/**
	 * Se o arquivo � 
	 *
	 * @var unknown_type
	 */
	private $tipoArquivo = '';

	/**
	 * Se os campos do arquivo tem tamanho fixo
	 * ou s�o delimitados por tab
	 *
	 * @var integer
	 */
	private $formato = KM_IMPORTADOR_FORMATO_FIXO;

	/**
	 * No caso de fetch, guarda a linha atualmente posicionada
	 *
	 * @var integer
	 */
	private $linhaAtual = '';

	/**
	 * Ultima linha a ser lida quando se usa a fun��o fetch
	 *
	 * @var integer
	 */
	private $linhaFinal = '';

	/**
	 * Primeira linha a ser lida no arquivo
	 *
	 * @var integer
	 */
	private $linhaInicial = 0;

	/**
	 * Fun��o que ser� chamada no caso de haver erro na importa��o
	 * aceita as constantes [TITULO] e [ERRO]
	 * ex: 
	 * //chama a fun��o nomeFuncao passando os dados do erro
	 * km_importador::onError='nomeFuncao('[TITULO]', '[ERRO]');
	 * 
	 * //aborta a execu��o mostrando uma mensagem na tela
	 * km_importador::onError='die("Erro na importa��o: [ERRO]<br>")');
	 * 
	 * @var string
	 */
	private $onError = '';

	/**
	 * Endere�o em disco do arquivo contendo o formato dos dados
	 * 
	 * @var string
	 */
	private $pathLayout = '';

	/**
	 * Delimitador de campo no caso do $formato ser
	 * 2=separados por caracter delimitador, geralmente � um \t (tab)
	 *
	 * @var string
	 */
	private $sepCampo = "\t";

	/**
	 * Separador de registros
	 *
	 * @var string
	 */
	private $sepRegistro = "\n";

	/**
	 * Faz consistencia do arquivo(testa se o arquivo esta ok), de acordo com padroes
	 * passados. Varias consistencias podem ser adicionadas e devem ter o retorno
	 * testado uma a uma
	 *
	 * @param integer $tipoConsistencia ver as constantes KM_IMPORTADOR_CONSISTIR_*
	 * @param mixed $valor1 seu preenchimento depende do tipo de consistencia executado
	 * @param mixed $valor2
	 * @param integer $linhaIni inicio de onde o teste ser� executado(come�a de "0"(zero)
	 * @param integer $linhaFin final do teste
	 * @param string $errMsg mensagem em caso de erro
	 * @return bool
	 */
	function addCheck($tipoConsistencia, $valor1 = '', $valor2 = '', $linhaIni = null, $linhaFin = null, $errMsg = null) {
		if ($this->getError()) {
			/*se j� tiver feito uma consistencia e dado erro n�o pode perder a mensagem*/
			return false;
		}
		/*se null recebe a primeira linha do layout*/
		if (is_null($linhaIni))
			$linhaIni = $this->linhaInicial;
			
		/*se null recebe a �ltima linha do layout*/
		if (is_null($linhaFin))
			$linhaFin = $this->linhaFinal;
			
		/*se o tamanho n�o existir para o tipo CONSISTIR_TAMANHO calcula*/
		if ($tipoConsistencia == KM_IMPORTADOR_CONSISTIR_TAMANHO && !$valor1) {
			foreach ($this->arrayLayout as $key => $aCampo)
				$valor1 += $aCampo['tam'];
		}
		for ($nLinhaAtual = $linhaIni; $nLinhaAtual <= $linhaFin; $nLinhaAtual++) {
			$aLinha = $this->getLine($nLinhaAtual);
			switch ($tipoConsistencia) {
				case KM_IMPORTADOR_CONSISTIR_COLUNAS:
					$aux = count($aLinha);
					if (count($aLinha) != $valor1) {
						/*coloca o numero de colunas no campo $msg*/
						$errMsg = 'N�mero de colunas inesperado.<br>Esperado: %s. Encontrado: %s';
						$this->setError(sprintf($errMsg, $valor1, $aux), $nLinhaAtual + 1);
						return false;
					}
					break;
				case KM_IMPORTADOR_CONSISTIR_FUNC:
					/*substitui strings como [NOME_CAMPO] para $aLinha["NOME_CAMPO"] para executar o eval*/
					$aux = str_replace(array('[', ']'), array('$aLinha["', '"]'), $valor1);
					if ($aLinha and !eval('error_reporting(E_ALL); return ' . $aux . ';')) {
						$this->setError($errMsg, $nLinhaAtual + 1);
						return false;
					}
					break;
				case KM_IMPORTADOR_CONSISTIR_REGEX:
					if (!ereg($valor2, $aLinha[$valor1])) {
						$this->setError($errMsg, $nLinhaAtual + 1);
						return false;
					}
					break;
				case KM_IMPORTADOR_CONSISTIR_TAMANHO:
					$aux = strlen($this->arrayDados[$nLinhaAtual]);
					if ($aux != $valor1) {
						$errMsg = 'Comprimento de linha inesperado.<br>Esperado: %s. Encontrado: %s';
						$this->setError(sprintf($errMsg, $valor1, $aux), $nLinhaAtual + 1);
						return false;
					}
					break;
				default:
					die('Tipo n�o implementado');
					break;
			}
		}
		return true;
	}

	/**
	 * Chama o handle de erro
	 *
	 */
	private function callErrorHandle() {
		if ($this->onError) {
			$aMsg['TITULO'] = 'Erro na importa��o';
			$aMsg['ERRO'] = 'Arquivo de dados: ' . $this->dados . '<br>';
			$aMsg['ERRO'] .= 'Arquivo de layout: ' . $this->pathLayout . '<br>';
			$aMsg['ERRO'] .= 'Linha: ' . $this->errorLine . '<br>';
			$aMsg['ERRO'] .= 'Erro: ' . $this->errorMsg . '<br>';
			$aMsg['ERRO'] = str_replace('"', '\\"', $aMsg['ERRO']);
			$aCampos = array('[TITULO]', '[ERRO]');
			$aValores = array('$aMsg["TITULO"]', '$aMsg["ERRO"]');
			eval(str_replace($aCampos, $aValores, $this->onError) . ';');
		}
	}

	/**
	 * Avan�a o cursor de leitura para a proxima linha
	 *
	 * @return bool
	 */
	function fetch() {
		if ($this->linhaAtual !== '') {
			$this->linhaAtual += 1;
		} else {
			$this->linhaAtual = $this->linhaInicial;
		}
		if ($this->linhaFinal && $this->linhaFinal < $this->linhaAtual) {
			return false;
		}
		
		$aResult = $this->getLine($this->linhaAtual);
		if ($aResult) {
			foreach ($aResult as $key => $val) {
				$this->$key = $val;
			}
		} else {
			/*caso seja uma linha em branco limpa os campos*/
			$this->clear();
		}
		
		return $aResult;
	}

	/**
	 * Reseta o cursor de leitura para a primeira linha
	 * 
	 * @todo verificar se esta fun��o esta sendo utilizada
	 * 
	 * @return bool
	 */
	function reset() {
		$this->linhaAtual = '';
	}

	/**
	 * Retorna a mensagem de erro formatada, contendo
	 * a mensagem e o numero da linha
	 *
	 * @return string
	 */
	function getError() {
		if ($this->errorLine !== '') {
			return 'Erro: ' . $this->errorMsg . '<br> Linha: ' . $this->errorLine;
		}
		return false;
	}

	/**
	 * Retorna somente a linha onde aconteceu a mensagem
	 * @see setError()
	 *
	 * @return integer
	 */
	function getErrorLine() {
		return $this->errorLine !== '' ? $this->errorLine : false;
	}

	/**
	 * Retorna somente a mensagem ocorrida
	 *
	 * @return string
	 */
	function getErrorMsg() {
		return $this->errorMsg;
	}

	/**
	 * Retorna um array contendo o nome dos campos
	 *
	 * @return array
	 */
	function getFields() {
		return array_keys($this->arrayLayout);
	}

	/**
	 * Posiciona o objeto sobre a linha e l� os dados de acordo com o layout
	 * se n�o for passado o parametro $num l� a ultima linha posicionada pelo fetch()
	 *
	 * @param integer $iNumeroLinha numero da linha a ser lido
	 * @param boolean $bParser se � para fazer transforma��es no dado ou se � para 
	 * devolver o original lido do arquivo
	 * @return array
	 */
	function getLine($iNumeroLinha = null, $bParser = true) {
		if (is_null($iNumeroLinha)) {
			$iNumeroLinha = $this->linhaAtual;
		}
		
		/*diz se existe a linha*/
		$ok = false;
		
		/*se a linha existe*/
		if (isset($this->arrayDados[$iNumeroLinha])) {
			/*se a linha tem dados, ignora o delimitador(no caso do formato delimitado)*/
			if (trim(str_replace($this->sepCampo, '', $this->arrayDados[$iNumeroLinha]))) {
				$ok = true;
			}
		}
		
		/*caso seja uma linha em branco*/
		if (!$ok) {
			return false;
		}
		
		/*importa as linhas*/
		$sLinha = $this->arrayDados[$iNumeroLinha];
		if ($this->tipoArquivo == KM_IMPORTADOR_FORMATO_DELIMITADO) {
			$sLinha = explode($this->sepCampo, $sLinha);
		}
		$retorno = array();
		foreach ($this->arrayLayout as $nmCampo => $aDefinicao) {
			/*guardar� o valor do campo na linha*/
			$valor = '';
			
			/*interpreta o valor do campo usando as como base a defini��es do campo no layout*/
			if ($this->tipoArquivo == KM_IMPORTADOR_FORMATO_FIXO) {
				$valor = substr($sLinha, $aDefinicao['ini'], $aDefinicao['tam']);
			} else {
				if (!isset($sLinha[$aDefinicao['pos']])) {
					$errMsg = 'Indice n�o encontrado<br>Indice: %s. Array: %s';
					$this->setError(sprintf($errMsg, $aDefinicao['pos'], print_r($sLinha, true)), __LINE__);
					return false;
				}
				$valor = $sLinha[$aDefinicao['pos']];
			}
			
			/*se � para fazer transforma��es no valor com base no tipo, ou se devolver� o valor original lido*/
			if ($bParser)
				$valor = $this->getType($aDefinicao['tipo'], $valor);
				
			/*adiciona o valor ao campo de retorno*/
			$retorno[$nmCampo] = $valor;
		}
		return $retorno;
	}

	/**
	 * Retorna o numero de linhas que o arquivo de dados contem
	 *
	 * @return integer
	 */
	function getNumLines() {
		return count($this->arrayDados);
	}

	/**
	 * Trata o $val de acordo com a defini��o de $defTipo {@link setLayout()}
	 *
	 * @param string $defTipo
	 * 
	 * @param string $val
	 */
	function getType($defTipo, $val) {
		$defTipo = explode(',', $defTipo);
		switch ($defTipo[0]) {
			case 'N':
				/*defini��o do tipo numerico*/
				$val = ereg_replace('[^0-9-]', '', $val);
				
				/*converte para inteiro ex: $val=000150 : 150*/
				$val = intval($val);
				
				/*impondo as casas decimais descritas no layout Ex: $val=150 e $defTipo[1]=2 : 150/100=1.5*/
				if (isset($defTipo[1])) {
					$val /= pow(10, $defTipo[1]);
				}
				break;
			case 'S':
				/*defini��o do tipo string*/
				$val = trim($val);
				break;
			case 'D':
				/**
				 * interpreta a data como foi definido que esta no arquivo,
				 * o formato especificado deve estar no mesmo formato de
				 * {@link http://br.php.net/strftime}
				 */
				$val = strptime($val, $defTipo[1]);
				
				/**
				 * transforma o array gerato num {@link http://br.php.net/time}
				 */
				$val = mktime($val['tm_hour'], $val['tm_min'], $val['tm_sec'], 1, $val['tm_yday'] + 1, 
								$val['tm_year'] + 1900);
				
				/**
				 * se foi especificado um formato de devolu��o faz a transforma��o
				 * o formato especificado deve estar no mesmo formato de
				 * {@link http://br.php.net/strftime}
				 */
				if (isset($defTipo[2]))
					$val = strftime($defTipo[2], $val);
					
				/*devolve o resultado do parse*/
				return $val;
				
				break;
			default:
				$errMsg = 'Tipo de campo n�o definido, tipo:"%s"';
				$this->setError(sprintf($errMsg, join(',', $defTipo)), __LINE__);
		}
		return $val;
	}

	/**
	 * Construtor da classe
	 *
	 * @param string $pathLayout endere�o em disco do arquivo contendo o formato dos dados
	 * @param string $dados endere�o do arquivo ou string de dados
	 * @param integer $formato 1=tamanho fixo 2=separados por caracter delimitador
	 * @param integer $origem 1=disco(gravado) 2=string
	 * @param string $onError Handler de erro
	 * @param string $sepCampo Delimitador de campo no caso do $formato=2
	 * @param string $sepRegistro Delimitador de novo registro
	 */
	function __construct($pathLayout, $dados, $formato = KM_IMPORTADOR_FORMATO_FIXO, $origem = KM_IMPORTADOR_ORIGEM_ARQUIVO, $onError = '', $sepCampo = "\t", $sepRegistro = "\n") {
		
		$this->pathLayout = $pathLayout;
		if ($origem == KM_IMPORTADOR_ORIGEM_ARQUIVO) {
			$this->dados = $dados;
		}
		if (strpos($onError, '\'') !== false) {
			trigger_error('Usando aspas simples para setar o $onError');
		}
		$this->onError = $onError;
		if (!is_file($pathLayout)) {
			$this->setError('Arquivo de layout n�o encontrato: ' . $pathLayout, __LINE__);
		}
		if ($origem == KM_IMPORTADOR_ORIGEM_ARQUIVO) {
			if (!is_file($dados)) {
				$this->setError('Arquivo de dados n�o encontrato: ' . $dados, __LINE__);
			}
			$arrayDados = file_get_contents($dados);
		} elseif ($origem == KM_IMPORTADOR_ORIGEM_STRING) {
			
			$arrayDados = $dados;
		} else {
			$this->setError('Tipo de arquivo invalido: ' . $formato, __LINE__);
		}
		if (!strlen($arrayDados)) {
			$this->setError('Arquivo de dados vazio', __LINE__);
		}
		$this->tipoArquivo = $formato;
		$this->sepRegistro = $sepRegistro;
		$this->sepCampo = $sepCampo;
		$this->setLayout($pathLayout);
		$this->arrayDados = explode($sepRegistro, str_replace("\r", '', trim($arrayDados)));
	}

	/**
	 * Adiciona um erro a classe
	 *
	 * @param string $msg
	 * @param integer $line
	 */
	function setError($msg, $line) {
		$this->errorMsg = $msg;
		$this->errorLine = $line;
		$this->callErrorHandle();
		if ($this->abortOnError) {
			die();
		}
	}

	/**
	 * Limpa o objeto dos valores atribuidos 
	 *
	 */
	function clear() {
		/*pega as propriedades padr�o da classe*/
		$aPropertysDefault = get_class_vars(get_class($this));
		
		/*pega todas as propriedades da classe (propriedades padr�o e criadas pelo layout)*/
		$aPropertys = array_keys(get_object_vars($this));
		
		/*vare o array $aPropertys excluindo de $this todos os campos que n�o est�o presentes em $aPropertysDefault*/
		foreach ($aPropertys as $key) {
			if (!isset($aPropertysDefault[$key]))
				unset($this->$key);
		}
	}

	/**
	 * L� um arquivo layout para a classe
	 *
	 * O arquivo deve estar em um dos formatos
	 * <code>
	 * $this->tipoArquivo = KM_IMPORTADOR_FORMATO_FIXO;
	 * 	//DEF_TIPO_CAMPO + "="(igualdade) + NOME_DO_CAMPO_SEM_ESPACO + "=" + tamanho a ser lido
	 * </code>
	 * 
	 * OU
	 * 
	 * <code>
	 * $this->tipoArquivo = KM_IMPORTADOR_FORMATO_DELIMITADO;
	 * 	//DEF_TIPO_CAMPO + "="(igualdade) + NOME_DO_CAMPO_SEM_ESPACO + "=" + indice de pos. do campo
	 * </code>
	 *
	 * @param string $arquivoLayout
	 * @param integer $linhaInicial usado para pular cabe�alhos de arquivos que n�o precisam ser lidos
	 * @param integer $linhaFinal usado para parar de ler um arquivo antes da linha final
	 */
	function setLayout($arquivoLayout, $linhaInicial = 0, $linhaFinal = null) {
		/*apaga os campos de um layout antigo se existir*/
		$this->arrayLayout = array();
		
		/*limpa os campos criados por algum fetch se existir*/
		$this->clear();
		
		$aux = explode("\n", file_get_contents($arquivoLayout));
		error_reporting(E_ALL);
		$inicio = 0;
		foreach ($aux as $val) {
			/*se encontrada, remove a parte dos comentarios do final da linha*/
			if (strpos($val, '//')) {
				list($val) = explode('//', $val);
			}
			
			if (!$val) {
				continue;
			}
			
			/*separando as partes da defini��o: tipo, nmcampo e tamanho(quando definido)*/
			$val = explode('=', $val);
			list($tipo, $campo) = $val;
			if ($this->tipoArquivo == KM_IMPORTADOR_FORMATO_FIXO) {
				$tamanho = $val[2];
			}
			
			/*chegando o nome do campo*/
			if (!ereg('^[A-Z0-9_]*$', $campo)) {
				$errMsg = 'Caracter invalido no nome do campo, nome: "%s" permitidos: [A-Z0-9_] linha: %s';
				die(sprintf($errMsg, $campo, __LINE__));
			}
			
			/*setando arrays*/
			if ($this->tipoArquivo == KM_IMPORTADOR_FORMATO_FIXO) {
				/*checando o tamanho*/
				if (!ereg('^[0-9].*$', $tamanho)) {
					$errMsg = 'Tamanho ou indice invalido para o campo: "%s" indice: "%s" linha: %s';
					die(sprintf($errMsg, $campo, $tamanho, __LINE__));
				}
				
				$tamanho = $tamanho * 1;
				$this->arrayLayout[$campo] = array(
													'tipo' => $tipo, 
													'tam' => $tamanho, 
													'ini' => $inicio, 
													'fin' => $inicio + $tamanho);
				$inicio += $tamanho;
			} else {
				$this->arrayLayout[$campo] = array('tipo' => $tipo, 'pos' => $inicio);
				$inicio++;
			}
		}
		ksort($this->arrayLayout);
		$this->setLine($linhaInicial, $linhaFinal);
	}

	/**
	 * Seta a linha inicial para come�ar o fetch
	 *
	 * @param integer $linhaInicial
	 * @param integer $linhaFinal
	 */
	function setLine($linhaInicial, $linhaFinal = null) {
		$this->linhaInicial = $linhaInicial;
		$this->linhaFinal = $linhaFinal;
		$this->reset();
	}
}
?>