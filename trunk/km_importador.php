<?php

/**
 * Arquivo que contem a classe de importação, com funções de consistencia
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
 * Consiste usando uma expressão regular passada como argumento
 * 
 * Ex: 
 * //confere o campo 'NM_CAMPO' usando a expressão regular '^[0-9]{8}$'
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_REGEX, 'NM_CAMPO', '^[0-9]{8}$');
 *
 */
define('KM_IMPORTADOR_CONSISTIR_REGEX', 3);

/**
 * Consiste utilizando um trecho que será interpretado pela função eval
 * geralmente um teste de igualdade ou uma função
 *
 * Ex: 
 * //confere se o campo 'VALOR1' é menor que 'VALOR2'
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_FUNC, '[VALOR1] < [VALOR2]');
 * 
 * //confere se o campo 'COD_TIPO' esta entre 1 e 5
 * KM_importador::addCheck(KM_IMPORTADOR_CONSISTIR_FUNC, '[COD_TIPO] >=1 && [COD_TIPO] <=5');
 * 
 * //confere o campo 'COD_EMPRESA' usando a função conferirEmpresa
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
 * Os campos do arquivo são delimitados pelo caracter definido 
 * em KM_importador::sepCampo
 * 
 * @todo Fazer opção para que o formato delimitado aceite tamanho maximo, pode ate usar
 * a mesma posição de tamanho do formato de tamanho fixo
 */
define('KM_IMPORTADOR_FORMATO_DELIMITADO', 2);

/**
 * A variavel $dados da instancia da classe é passada como um
 * endereço de arquivo
 */
define('KM_IMPORTADOR_ORIGEM_ARQUIVO', 1);

/**
 * A variavel $dados da instancia da classe é passada como um
 * endereço de arquivo
 */
define('KM_IMPORTADOR_ORIGEM_STRING', 2);

/**
 * classe para importação de arquivos
 *
 */
class KM_importador {

	/**
	 * Construtor da classe
	 *
	 * @param string $pathLayout endereço em disco do arquivo contendo o formato dos dados
	 * @param string $dados endereço do arquivo ou string de dados
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
			$this->setError('Arquivo de layout não encontrato: ' . $pathLayout, __LINE__);
		}
		if ($origem == KM_IMPORTADOR_ORIGEM_ARQUIVO) {
			if (!is_file($dados)) {
				$this->setError('Arquivo de dados não encontrato: ' . $dados, __LINE__);
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
		$this->arrayDados = explode($sepRegistro, str_replace("\r", '', $arrayDados));
	}

	/**
	 * Se é para parar a execução do php com um die() caso
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
	 *  Array('tam'=>int(tamanho), 'tipo'(¹)=>String(tipo do campo), 'ini'=>int(inicio), 'fin'=>int(final)
	 *    OU
	 *  //Caso o arquivo seja separado por tab
	 *  Array('pos'=>int(posição do campo no arquivo))
	 * )
	 * 
	 * (¹) As definições dos tipos segue o padrão ditado no anexo 'DOC - DSV - KM_importador.doc'
	 * 
	 * @var array
	 */
	private $arrayLayout = array();

	/**
	 * Dados para importação
	 * 
	 * @var string
	 */
	private $dados = '';

	/**
	 * Linha onde aconteceu o erro, se estiver na parte
	 * de consistencia retornará a linha do arquivo de dados,
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
	 * Se os campos do arquivo tem tamanho fixo
	 * ou são delimitados por tab
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
	 * Ultima linha a ser lida quando se usa a função fetch
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
	 * Função que será chamada no caso de haver erro na importação
	 * aceita as constantes [TITULO] e [ERRO]
	 * ex: 
	 * //chama a função nomeFuncao passando os dados do erro
	 * km_importador::onError='nomeFuncao('[TITULO]', '[ERRO]');
	 * 
	 * //aborta a execução mostrando uma mensagem na tela
	 * km_importador::onError='die("Erro na importação: [ERRO]<br>")');
	 * 
	 * @var string
	 */
	private $onError = '';

	/**
	 * Endereço em disco do arquivo contendo o formato dos dados
	 * 
	 * @var string
	 */
	private $pathLayout = '';

	/**
	 * Delimitador de campo no caso do $formato ser
	 * 2=separados por caracter delimitador, geralmente é um \t (tab)
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
	 * @param mixed $valor2 seu preenchimento depende do tipo de consistencia executado
	 * @param string $errMsg mensagem em caso de erro
	 * @return bool
	 */
	public function addCheck($tipoConsistencia, $valor1 = '', $valor2 = '', /*$linhaIni = null, $linhaFin = null, */$errMsg = null) {
		if ($this->getError()) {
			/*se já tiver feito uma consistencia e dado erro não pode perder a mensagem*/
			return false;
		}
		/* linha inicial de onde o teste será executado(começa de "0"(zero))*/
		$linhaIni = $this->linhaInicial;
		
		$this->setLine($linhaIni);
		while ($this->fetch()) {
			$linha = $this->getLine();
			switch ($tipoConsistencia) {
				case KM_IMPORTADOR_CONSISTIR_COLUNAS:
					$aux = count($linha);
					if (count($linha) != $valor1) {
						/*coloca o numero de colunas no campo $msg*/
						$errMsg = 'Número de colunas inesperado.<br>Esperado: %s. Encontrado: %s';
						$this->setError(sprintf($errMsg, $valor1, $aux), $this->linhaAtual + 1);
						$this->reset();
						return false;
					}
					break;
				case KM_IMPORTADOR_CONSISTIR_FUNC:
						/*substitui strings como [NOME_CAMPO] para $linha["NOME_CAMPO"] para executar o eval*/
						$aux = str_replace(array('[', ']'), array('$linha["', '"]'), $valor1);
					if ($linha and !eval('error_reporting(E_ALL); return ' . $aux . ';')) {
						$this->setError($errMsg, $this->linhaAtual + 1);
						$this->reset();
						return false;
					}
					break;
				case KM_IMPORTADOR_CONSISTIR_REGEX:
					if (!ereg($valor2, $linha[$valor1])) {
						$this->setError($errMsg, $this->linhaAtual + 1);
						$this->reset();
						return false;
					}
					break;
				case KM_IMPORTADOR_CONSISTIR_TAMANHO:
					/*faz consistencia usando a soma do tamanho dos campos no layout*/
					if (!$valor1) {
						foreach ($this->arrayLayout as $key => $campo) {
							$valor1 += $campo['tam'];
						}
					}
					
					/*executa a validação*/
					$aux = strlen($this->arrayDados[$this->linhaAtual]);
					if ($aux != $valor1) {
						$errMsg = 'Comprimento de linha inesperado.<br>Esperado: %s. Encontrado: %s';
						$this->setError(sprintf($errMsg, $valor1, $aux), $this->linhaAtual + 1);
						$this->reset();
						return false;
					}
					break;
				default:
					die('Tipo não implementado');
					break;
			}
		}
		$this->reset();
		return true;
	}

	/**
	 * Chama a função de tratamento de erro registrada
	 *
	 */
	private function callErrorHandle() {
		if ($this->onError) {
			$aMsg['TITULO'] = 'Erro na importação';
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
	 * Avança o cursor de leitura para a proxima linha
	 *
	 * @return bool
	 */
	public function fetch() {
		if ($this->linhaAtual !== '') {
			$this->linhaAtual += 1;
		} else {
			$this->linhaAtual = $this->linhaInicial;
		}
		if ($this->linhaFinal && $this->linhaFinal < $this->linhaAtual) {
			return false;
		}
		return $this->getLine($this->linhaAtual);
	}

	/**
	 * Reseta o cursor de leitura para a primeira linha
	 * 
	 * @todo verificar se esta função esta sendo utilizada
	 * 
	 * @return bool
	 */
	public function reset() {
		$this->linhaAtual = '';
	}

	/**
	 * Retorna a mensagem de erro formatada, contendo
	 * a mensagem e o numero da linha
	 *
	 * @return string
	 */
	public function getError() {
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
	public function getErrorLine() {
		return $this->errorLine !== '' ? $this->errorLine : false;
	}

	/**
	 * Retorna somente a mensagem ocorrida
	 *
	 * @return string
	 */
	public function getErrorMsg() {
		return $this->errorMsg;
	}

	/**
	 * Retorna um array contendo o nome dos campos
	 *
	 * @return array
	 */
	public function getFields() {
		return array_keys($this->arrayLayout);
	}

	/**
	 * Posiciona o objeto sobre a linha e lê os dados de acordo com o layout
	 * se não for passado o parametro $num lê a ultima linha posicionada pelo fetch()
	 *
	 * @param integer $num
	 * @return array
	 */
	public function getLine($num = null) {
		if (is_null($num)) {
			$num = $this->linhaAtual;
		}
		
		/*diz se existe a linha*/
		$ok = false;
		
		/*se a linha existe*/
		if (isset($this->arrayDados[$num])) {
			/*se a linha tem dados, ignora o delimitador(no caso do formato delimitado)*/
			if (trim(str_replace($this->sepCampo, '', $this->arrayDados[$num]))) {
				$ok = true;
			}
		}
		
		/*caso não exista a linha ou seja uma linha em branco*/
		if (!$ok) {
			/*limpa os campos*/
			while (list($campo) = each($this->arrayLayout)) {
				unset($this->$campo);
			}
			return false;
		}
		
		/*importa as linhas*/
		$linha = $this->arrayDados[$num];
		if ($this->tipoArquivo == KM_IMPORTADOR_FORMATO_DELIMITADO) {
			$linha = explode($this->sepCampo, $linha);
		}
		$retorno = array();
		foreach ($this->arrayLayout as $campo => $def_campo) {
			if ($this->tipoArquivo == KM_IMPORTADOR_FORMATO_FIXO) {
				$aux = substr($linha, $def_campo['ini'], $def_campo['tam']);
				$this->$campo = $retorno[$campo] = $this->getType($def_campo['tipo'], $aux);
			} else {
				if (!isset($linha[$def_campo['pos']])) {
					$errMsg = 'Indice não encontrado<br>Indice: %s. Array: %s';
					$this->setError(sprintf($errMsg, $def_campo['pos'], print_r($linha, true)), __LINE__);
					return false;
				}
				$aux = $linha[$def_campo['pos']];
				$this->$campo = $retorno[$campo] = $this->getType($def_campo['tipo'], $aux);
			}
		}
		return $retorno;
	}

	/**
	 * Retorna o numero de linhas que o arquivo de dados contem
	 *
	 * @return integer
	 */
	public function getNumLines() {
		return count($this->arrayDados);
	}

	/**
	 * Trata o tipo do dado @see setLayout()
	 *
	 * @param string $defTipo
	 * @param string $val
	 */
	private function getType($defTipo, $val) {
		$defTipo = explode(',', $defTipo);
		switch ($defTipo[0]) {
			case 'N':
				/*definição do tipo numerico*/
				$val = ereg_replace('[^0-9-]', '', $val);
				
				/*converte para inteiro ex: $val=000150 : 150*/
				$val = intval($val);
				
				/*impondo as casas decimais descritas no layout Ex: $val=150 e $defTipo[1]=2 : 150/100=1.5*/
				if (isset($defTipo[1])) {
					$val /= pow(10, $defTipo[1]);
				}
				break;
			case 'S':
				/*definição do tipo string*/
				$val = trim($val);
				break;
			default:
				$errMsg = 'Tipo de campo não definido, tipo:"%s"';
				$this->setError(sprintf($errMsg, join(',', $defTipo)), __LINE__);
		}
		return $val;
	}

	/**
	 * Adiciona um erro a classe
	 *
	 * @param string $msg
	 * @param integer $line
	 */
	private function setError($msg, $line) {
		$this->errorMsg = $msg;
		$this->errorLine = $line;
		$this->callErrorHandle();
		if ($this->abortOnError) {
			die();
		}
	}

	/**
	 * Lê um arquivo layout para a classe
	 *
	 * O arquivo deve estar em um dos formatos
	 * 
	 * $this->tipoArquivo == KM_IMPORTADOR_FORMATO_FIXO
	 * 	DEF_TIPO_CAMPO + "="(igualdade) + NOME_DO_CAMPO_SEM_ESPACO + "=" + tamanho a ser lido
	 * 
	 * OU
	 * 
	 * $this->tipoArquivo == KM_IMPORTADOR_FORMATO_DELIMITADO
	 * 	DEF_TIPO_CAMPO + "="(igualdade) + NOME_DO_CAMPO_SEM_ESPACO + "=" + indice de pos. do campo
	 *
	 * @param string $arquivoLayout
	 * @param integer $linhaInicial usado para pular cabeçalhos de arquivos que não precisam ser lidos
	 * @param integer $linhaFinal usado para parar de ler um arquivo antes da linha final
	 */
	public function setLayout($arquivoLayout, $linhaInicial = 0, $linhaFinal = null) {
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
			
			/*separando as partes da definição: tipo, nmcampo e tamanho(quando definido)*/
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
		$this->setLine($linhaInicial, $linhaFinal);
	}

	/**
	 * Seta a linha inicial para começar o fetch
	 *
	 * @param integer $linhaInicial
	 * @param integer $linhaFinal
	 */
	private function setLine($linhaInicial, $linhaFinal = null) {
		$this->linhaInicial = $linhaInicial;
		$this->linhaFinal = $linhaFinal;
		$this->reset();
	}
}
?>