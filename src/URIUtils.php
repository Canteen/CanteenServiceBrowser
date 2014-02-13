<?php

/**
*  @module Canteen\ServiceBrowser
*/
namespace Canteen\ServiceBrowser
{
	/**
	*  Utility function for handling the URI request strings
	*  @class URIUtils
	*/
	abstract class URIUtils
	{
		/**
		*  Process the URI as an array with different pieces
		*  @method processURI
		*  @static
		*  @param {String} uriRequest The URI request being made
		*  @param {int} ignore The number of elements to exclude from the beginning
		*  @return {Array} The URI request where each stub is an element
		*/
		public static function processURI($uriRequest, $ignore)
		{			
			$uri = explode('/', $uriRequest);
			$base = array_slice($uri, 0, $ignore);
			$uri = array_slice($uri, $ignore); // don't use the name of the page
			
			// Sanitize the result to remove non charaters
			for($i = 0; $i < count($uri); $i++)
			{
				$uri[$i] = preg_replace('/[^a-zA-Z0-9\_\-\,%]/', '', $uri[$i]);
			}
			
			function cleanArg($arg)
			{
				if (is_array($arg))
				{
					foreach($arg as $i=>$a)
					{
						$arg[$i] = cleanArg($a);
					}
					return $arg;
				}
				else
				{
					return trim(urldecode($arg));
				}
			}
			
			// Grab the rest of the uri arguments
			$args = count($uri) > 2 ? array_slice($uri, 2) : '';
			
			// Check to see if we should make an array of any of the arguments
			if ($args)
			{				
				foreach($args as $i => $arg)
				{
					$args[$i] = (strpos($arg, ',') !== false) ?
						cleanArg(explode(',', $arg)) : // Split into an array
						cleanArg($arg);  // Decode the arrray
				}
			}
			
			// Turn into a result
			return [
				'base' => implode('/', $base),
				'service' => ifsetor($uri[0], ''),
				'call' => ifsetor($uri[1], ''),
				'args' => $args
			];
		}

		/**
		*  Convert a uri (eg. get-all-users) to method call (e.g. getAllUsers)
		*  @method uriToMethodCall 
		*  @static
		*  @param {String} uri The input uri (lowercase, hyphen separated)
		*  @return {String} The method call name (lower, camel-cased)
		*/
		public static function uriToMethodCall($uri)
		{
			if (preg_match('/^[a-z]+\-[a-z\-]+$/', $uri))
			{
				$parts = explode('-', $uri);
				
				for($i = 1; $i < count($parts); $i++)
				{
					$parts[$i] = ucfirst($parts[$i]);
				}
				return implode('', $parts);
			}
			return $uri;
		}
		
		/**
		*  Convert a method call (getAllUsers) to a URI stub (get-all-users)
		*  @method methodCallToUri
		*  @static
		*  @param {String} methodCall The name of the method
		*  @return {String} The method call as a URI stub
		*/
		public static function methodCallToUri($methodCall)
		{
			return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $methodCall));
		}
	}
}