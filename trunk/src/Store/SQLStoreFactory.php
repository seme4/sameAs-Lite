<?php
/**
 * SameAs Lite
 *
 * Factory to allow user to create a SQL Store based on the DSN prefix
 *
 * @package   SameAsLite
 * @author    Seme4 Ltd <sameAs@seme4.com>
 * @copyright 2009 - 2014 Seme4 Ltd
 * @link      http://www.seme4.com
 * @version   0.0.1
 * @license   MIT Public License
 *
 * MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace SameAsLite;

/**
 * Class creates an SQL Store from a DSN instead of an option array
 * TODO: INCOMPLETE
 */
class SQLStoreFactory {


	/**
	 * Constructor to prevent class from instantiation
	 */
	private function __construct(){}


	/**
	 * TODO: complete this function (does not work great with sqlite)
	 *
	 * @param string $name     The name of the store
	 * @param string $dsn      The DSN used to create the \PDO object
	 * @param array  $options  Options
	 *
	 * @throws \Exception If the DSN is not compatable with the Store
	 */
	public static function create($name, $dsn, array $options = array()){
		throw new \Exception('CLASS NOT COMPLETE, DO NOT USE');

		// Get the subclasses of MySQL
		$subclasses = self::getSubclassesOf('Store\SQLStore');

		// Split DSN
		list($prefix, $settingsString) = explode(':' $dsn);

		// Get the settings
		$classOptions = null;
		$class = null;
		while(!isset($class) || count($subclasses) > 0){
			$c = array_pop($subclasses);
			$s = $c::getFactorySettings();
			if($s['dsn_prefix'] === $prefix)){
				$class = $c;
				$classOptions = $s;
			}
		}

		if(!isset($class)){
			throw new \Exception('No class found to handle the given DSN');
		}

		$settings = [];
		array_walk(explode(':', $settingsString), function($i){
			list($a, $b) = explode('=', $i);
			$settings[$a] = $b;
		});

		

	}





	/**
	 * Returns the subclasses of a given parent class
	 * Taken from http://stackoverflow.com/questions/3470008/how-to-obtain-all-subclasses-of-a-class-in-php
	 *
	 * @param mixed $parent Class name of parent class
	 * @return string[] Array of classes that are a subclass of $parent
	 */
	private static function getSubclassesOf($parent) {
	    $result = array();
	    foreach (get_declared_classes() as $class) {
	        if (is_subclass_of($class, $parent))
	            $result[] = $class;
	    }
	    return $result;
	}

}

