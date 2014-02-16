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