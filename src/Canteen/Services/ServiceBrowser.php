<?php

/**
*  @module Canteen\Services
*/
namespace Canteen\Services
{
	use Canteen\Utilities\URIUtils;
	use Canteen\Parser\Parser;
	use Canteen\Logger\Logger;
	use Canteen\HTML5\SimpleList;
	use \ReflectionClass;
	use \Exception;
	
	/**
	*  Web debugging interface for browsing and testing Services within the CanteenFramework.
	*  Located in the namespace __Canteen\Services\ServiceBrowser__.
	*  @class ServiceBrowser
	*  @constructor
	*  @param {Dictionary} aliases The collection of custom service aliases to class names
	*  @param {String} basePath The full path to the base site path
	*  @param {String} browserUri The URI stub for the browser
	*  @param {String} uriRequest The full URI requested on the site
	*  @param {Parser} [parser=null] Reference to the Canteen Parser
	*/
	class ServiceBrowser
	{
		/** 
		*  These are the services built into Canteen
		*  @property {Array} _builtInAliases 
		*  @private
		*/
		private $_builtInAliases = [
			'user',
			'page',
			'time',
			'config'
		];
		
		/** 
		*  The collection of all service aliases, Canteen and Custom
		*  @property {Array} _aliases
		*  @private
		*/
		private $_aliases;
		
		/** 
		*  The uri for this browser
		*  @property {String} _browserUri
		*  @private
		*/
		private $_browserUri;

		/** 
		*  The Site's basePath
		*  @property {String} _basePath
		*  @private
		*/
		private $_basePath;

		/** 
		*  The full browser URI path
		*  @property {String} _uriRequest
		*  @private
		*/
		private $_uriRequest;
		
		/** 
		*  The full browser URI as pieces
		*  @property {Array} _uri 
		*  @private
		*/
		private $_uri;

		/** 
		*  Reference to the parser
		*  @property {Parser} _parser 
		*  @private
		*/
		private $_parser;

		/**
		*  Constructor
		*/
		public function __construct(array $aliases, $basePath, $browserUri, $uriRequest, Parser $parser=null)
		{
			$this->_aliases = $aliases;
			$this->_parser = $parser ? $parser : new Parser();
			$this->_browserUri = $browserUri;
			$this->_uriRequest = $uriRequest;
			$this->_basePath = $basePath;
		}

		/**
		*  Get a service name by an alias
		*  @method getServiceNameByAlias
		*  @param {String} The alias
		*  @return {String} The name of the Service class
		*/
		private function getServiceNameByAlias($alias)
		{
			$aliases = array_merge($this->_aliases, $this->_builtInAliases);
			return ifsetor($aliases[$alias]);
		}
		
		/**
		*  Create the browser request
		*  @method handle
		*/
		public function handle()
		{
			$this->_uri = URIUtils::processURI(
				$this->_uriRequest, 
				count(explode('/', $this->_browserUri))
			);
			
			// Generate the output, if any
			$output = '';
			$serviceName = '';
			$serviceAlias = '';
			
			if ($serviceAlias = ifsetor($this->_uri['service']))
			{
				$serviceName = $this->getServiceNameByAlias($serviceAlias);
				$args = ifsetor($this->_uri['args']);
				
				$argsName = $this->displayArgs($args);
				
				// if there's a call parse that
				if ($callAlias = ifsetor($this->_uri['call'])) 
				{
					$service = new $serviceName;
					
					$callName = URIUtils::uriToMethodCall($callAlias);					
					$numParams = 0;

					if (!method_exists($service, $callName))
					{
						$output .= html('span.prompt', 'No service call matching '.$callName);
					}
					else
					{
						$reflector = new ReflectionClass($serviceName);
						$parameters = $reflector->getMethod($callName)->getParameters();
						$numParams = count($parameters);
						$numRequiredParams = $numParams;
						
						$output .= html('h2', $serviceName.'.'.$callName.'('.$argsName.')');
						
						if ($numParams && !$args && $numParams != $args)
						{
							$inputs = '';
							$i = 0;
							foreach($parameters as $param)
							{
								$default = '';
								$className = 'required';
								if ($param->isDefaultValueAvailable())
								{
									$numRequiredParams--;
									$default = $param->getDefaultValue();
									if ($default === null) $default = 'null';
									if ($default === false) $default = 'false';
									$className = 'optional';
								}
								$inputType = $param->getName() == 'password' ? 'password' : 'text';
								$inputs .= html('label', $param->getName(), 'for:serviceInput'.$i);
								$input = html('input', [
									'type' => $inputType,
									'id' => 'serviceInput'.$i,
									'class' => 'text '.$className,
									'name' => 'arguments[]',
									'value' => ifsetor($default, '')								
								]);
								$inputs .= $input . html('br'); 
								$i++;
							}
							
							$fieldset = html('fieldset', [
								html('legend', "$numParams additional argument(s) required for this method"),
								html('div', $inputs),
								html('div.formButtons', html('input.submit type=submit value="Call Service"'))
							]);
							
							$action = $this->_basePath . $this->_browserUri.'/'.$serviceAlias.'/'.$callAlias;
							$output .= html('form#formInputs method=get action='.$action, $fieldset);
						
							// If the function has defaults for all params,
							// we'll show the form AND the default output
							if ($numRequiredParams === 0)
							{
								$output .= $this->callMethod(array($service, $callName));
							}
						}
						else
						{
							$output .= $this->callMethod(array($service, $callName), $args);
						}
					}
				}
			}
			
			$data = [
				'output' => $output,
				'services' => $this->getServicesList(),
				'methods' => $this->getMethodsList($serviceName, $serviceAlias),
				'logger' => ''
			];

			if (class_exists('Canteen\Logger\Logger'))
			{
				$data['logger'] = (string)Logger::instance()->render();
			}
			
			return $this->_parser->parseFile(__DIR__.'/ServiceBrowser.html', $data);
		}
		
		/**
		*  Call the method and get the result
		*  @method callMethod
		*  @private
		*  @param {String|Array} call The user function to call
		*  @param {Array} [args=null] The optional arguments to pass to the user function
		*  @return {String} The HTML result of the call or stack trace
		*/
		private function callMethod($call, $args=null)
		{
			try
			{
				$result = $args ? 
					call_user_func_array($call, $args) : 
					call_user_func($call);
				
				$return = print_r($result, true);
				$return = !$return ? 'null' : $return;
			}
			catch(Exception $e)
			{				
				return html('div', $e->getMessage() 
					. ' (code: '.$e->getCode().')'
					. (string)new SimpleList(
						$this->getFormattedTrace($e), null, 'ol'), 
						'class=exception'
					);
			}
			return html('pre', $return);
		}

		/**
		*  A utility function to formatted the exception stack trace
		*  @method getFormattedTrace
		*  @private
		*  @param {Exception} e The exception to convert to trace
		*  @return {Array} The collection of arrays
		*/
		private function getFormattedTrace(Exception $e)
		{
			$trace = $e->getTraceAsString();
			$trace = preg_split('/\#[0-9]+ /', $trace);
			$stack = [];
			foreach($trace as $t)
			{
				$t = trim($t);
				if (!$t) continue;
				$stack[] = str_replace(self::$rootPath, '', $t);
			}
			return $stack;
		}
		
		/**
		*  The name of the class
		*  @method getMethodsList
		*  @private
		*  @return {String} HTML mark-up for methods lis
		*/
		private function getMethodsList($serviceName, $serviceAlias)
		{
			$res = '';
			
			if (!$serviceName) return $res;
			
			// Get the list of methods
			$reflector = new ReflectionClass($serviceName);
			$methods = $reflector->getMethods();
			
			// Sort the methods alphabetically by name
			$names = [];
			foreach ($methods as $key => $method)
			{
				$names[$key] = $method->name;
			}
			array_multisort($names, SORT_ASC, $methods);
			
			$ul = html('ul');
			
			foreach($methods as $method)
			{
				// Remove all inherited methods
				if ($method->class != $serviceName) continue;

				// For services ignore constructor, static and protected
				if ($method->isConstructor() 
					|| $method->isStatic() 
					|| $method->isProtected()
					|| $method->isPrivate()
					|| substr($method->name, 0, 2) == '__') continue;
				
				$link = html('a', $method->name);
				$link->href = $this->_basePath . $this->_browserUri.'/'.$serviceAlias.'/'.URIUtils::methodCallToUri($method->name);				
				$ul->addChild(html('li', $link));	
			}
			return html('h2', $this->simpleName($serviceName)) . $ul;
		}
		
		/**
		*  Get a list of services
		*  @method getServicesList
		*  @private
		*  @return {String} HTML list of services
		*/
		private function getServicesList()
		{
			// Generate the services
			$ul = html('ul');
			if ($this->_aliases)
			{
				foreach ($this->_aliases as $alias=>$className)
				{
					$link = html('a', $this->simpleName($className));
					$link->href = $this->_basePath . $this->_browserUri.'/'.$alias;
					if (in_array($alias, $this->_builtInAliases))
					{
						$link->class = 'internal';
					}
					$ul->addChild(html('li', $link));
				}
			}
			return $ul;
		}
		
		/**
		*  Get the name of the class only
		*  @method simpleName
		*  @private
		*  @param {String} className The full name of the class with namespace 
		*  @return {String} The last class name
		*/
		private function simpleName($className)
		{
			return substr($className, strrpos($className, '\\')+1);
		}
		
		/**
		*  Display the arguments as a string
		*  @method displayArgs
		*  @param {Array} args The arguments array
		*  @return {String} The string representation of the arguments, comma-separated
		*/
		private function displayArgs($args)
		{
			$res = [];
			if ($args)
			{
				foreach($args as $i=>$val)
				{
					if ($val === null)
					{
						$res[$i] = 'null';
						continue;
					}
					
					if ($val === false)
					{
						$res[$i] = 'false';
						continue;
					}
					
					if ($val === true)
					{
						$res[$i] = 'true';
						continue;
					}
					
					$res[$i] = (is_array($args[$i])) ?
						'['.$this->displayArgs($val).']':
						(preg_match('/^[0-9\.]*$/', $val) ? 
							(int)$val : "'$val'");
				}
			}
			return $res ? implode(', ', $res) : '';
		}
	}
}