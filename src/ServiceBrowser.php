<?php

/**
*  @module Canteen\ServiceBrowser
*/
namespace Canteen\ServiceBrowser
{
	use Canteen\Authorization\Privilege;
	use Canteen\Utilities\Plugin;
	use Canteen\Parser\Parser;
	use Canteen\Logger\Logger;
	use Canteen\Services\Service;
	use Canteen\HTML5\SimpleList;
	use \ReflectionClass;
	use \Exception;
	
	/**
	*  Web debugging interface for browsing and testing Services within the CanteenFramework.
	*  Located in the namespace __Canteen\ServiceBrowser__.
	*  @class ServiceBrowser
	*  @extends Plugin
	*/
	class ServiceBrowser extends Plugin
	{
		/** 
		*  These are the services built into Canteen
		*  @property {Array} _builtInAliases 
		*  @private
		*/
		private $_builtInAliases = [
			'config',
			'page',
			'time',
			'user'
		];
		
		/** 
		*  The collection of all service aliases, Canteen and Custom
		*  @property {Array} _services
		*  @private
		*/
		private $_services;
		
		/** 
		*  The alias for the service
		*  @property {String} _serviceAlias
		*  @private
		*/
		private $_serviceAlias;

		/** 
		*  The main uri for the browser
		*  @property {String} BROWSER_URI
		*  @final
		*  @static
		*  @default 'browser'
		*/
		const BROWSER_URI = 'browser';

		/**
		*  @method activate
		*/
		public function activate()
		{
			// Check for admin privilege
			$isAdmin = ($this->settings->userPrivilege == Privilege::ADMINISTRATOR);

			// Setup the service browser for debug mode if we're running locally
			// or we are an administrator privilege
			if (($this->settings->local || $isAdmin) && $this->settings->debug)
			{
				$this->_services = Service::getAll();
				$this->site->route(
					'/'.self::BROWSER_URI.'(/@service:[a-zA-Z0-9\-]+(/@call:[a-zA-Z0-9\-]+))/',
					[$this, 'handle']
				);
			}
		}
		
		/**
		*  Create the browser request
		*  @method handle
		*/
		public function handle($serviceAlias=null, $callAlias=null)
		{			
			// Generate the output, if any
			$output = '';
			$serviceClass = '';

			$this->_serviceAlias = $serviceAlias;
			
			if ($serviceAlias)
			{
				$service = ifsetor($this->_services[$serviceAlias]);
				$serviceClass = get_class($service);
				$args = ifsetor($_POST['args']);
				
				$argsName = $this->displayArgs($args);
				
				// if there's a call parse that
				if ($callAlias) 
				{					
					$callName = URIUtils::uriToMethodCall($callAlias);					
					$numParams = 0;

					if (!method_exists($service, $callName))
					{
						$output .= html('span.prompt', 'No service call matching '.html('strong', $callName));
					}
					else
					{
						$reflector = new ReflectionClass($serviceClass);
						$parameters = $reflector->getMethod($callName)->getParameters();
						$numParams = count($parameters);
						$numRequiredParams = $numParams;
						
						$output .= html('h2', $serviceClass.'.'.$callName.'('.$argsName.')');
						
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
								$label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $param->getName());
								$inputs .= html('label', ucfirst($label), 'for:serviceInput'.$i);
								$input = html('input', [
									'type' => $inputType,
									'id' => 'serviceInput'.$i,
									'class' => 'text '.$className,
									'name' => 'args[]',
									'value' => ifsetor($default, '')								
								]);
								$inputs .= $input; 
								$i++;
							}
							
							$fieldset = html('fieldset', [
								html('legend', "$numParams additional argument(s) required for this method"),
								html('div', $inputs),
								html('div.formButtons', html('input.submit type=submit value="Call Service"'))
							]);
							
							$action = $this->settings->basePath . self::BROWSER_URI.'/'.$serviceAlias.'/'.$callAlias;
							$output .= html('form#formInputs method=post action='.$action, $fieldset);
						
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
				'methods' => $this->getMethodsList($serviceClass),
				'logger' => ''
			];

			if (class_exists('Canteen\Logger\Logger'))
			{
				$data['logger'] = (string)Logger::instance()->render();
			}
			
			echo $this->parser->parseFile(__DIR__.'/ServiceBrowser.html', $data);
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
				$stack[] = $t;
			}
			return $stack;
		}
		
		/**
		*  The name of the class
		*  @method getMethodsList
		*  @private
		*  @param {String} serviceClass The name of the service class
		*  @return {String} HTML mark-up for methods lis
		*/
		private function getMethodsList($serviceClass)
		{
			$res = '';
			
			if (!$serviceClass) return $res;
			
			// Get the list of methods
			$reflector = new ReflectionClass($serviceClass);
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
				if ($method->class != $serviceClass) continue;

				// For services ignore constructor, static and protected
				if ($method->isConstructor() 
					|| $method->isStatic() 
					|| $method->isProtected()
					|| $method->isPrivate()
					|| substr($method->name, 0, 2) == '__') continue;
				
				$link = html('a', $method->name);
				$link->href = $this->settings->basePath . self::BROWSER_URI.'/'.$this->_serviceAlias.'/'.URIUtils::methodCallToUri($method->name);				
				$ul->addChild(html('li', $link));	
			}
			return html('h2', $this->simpleName($serviceClass)) . $ul;
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
			if ($this->_services)
			{
				krsort($this->_services);
				
				foreach ($this->_services as $alias=>$classObject)
				{
					$link = html('a', $this->simpleName(get_class($classObject)));
					$link->href = $this->settings->basePath . self::BROWSER_URI.'/'.$alias;
					if (in_array($alias, $this->_builtInAliases))
					{
						$link->class = 'internal';
						$ul->addChild(html('li', $link));
					}
					else
					{
						$ul->addChildAt(html('li', $link), 0);
					}
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