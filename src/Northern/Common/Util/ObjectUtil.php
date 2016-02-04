<?php
/*!
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

namespace Northern\Common\Util;

use Symfony\Component\Validator\ValidatorBuilder;

class ObjectUtil {

	/**
	 * This method applies a given set of values to a given object.
	 *
	 * The array with values supplied must be actual values of the
	 * object and must be exposed through a corresponding setter method. 
	 * E.g. if you wish to set the value on a property called 'firstname'
	 * then the object needs to have a 'setFirstname' setter method. If 
	 * the setter is not available the property will not be set and the
	 * corresponding value will not be applied.
	 *
	 * @param  object $object
	 * @param  array $values
	 */
	public static function apply( $object, array $values )
	{
		foreach( $values as $property => $value )
		{
			// Check if the value is a map (associative array) by checking for
			// a zero index.
			if( is_array( $value ) AND ! isset( $value[0] ) )
			{
				$method = "get".ucfirst( $property );
				
				if( method_exists( $object, $method ) )
				{
					//$object->{$method}()->apply( $value );
					static::apply( $object->{$method}(), $value );
				}
			}
			else
			// Check if the value is a collection, i.e. an indexed array. If this
			// is the case then the $value is an interable array. If the object
			// has an "add" method for the property then we call this x times.
			if( is_array( $value ) AND isset( $value[0] ) )
			{
				$method = "add".ucfirst( $property );
				
				if( method_exists( $object, $method ) )
				{
					foreach( $value as $arguments )
					{
						if( ! is_array( $arguments ) )
						{
							$arguments = array( $arguments );
						}
						
						call_user_func_array( array( $object, $method ), $arguments );
					}
				}
			}
			else
			{
				// Check if the value has a corresponding setter method
				// on the object.
				$method = "set".ucfirst( $property );
				
				if( method_exists( $object, $method ) )
				{
					$object->{$method}( $value );
				}
			}
		}
	}
	
	/**
	 * This method will validate an object with the specified values.
	 *
	 * If any of the values does not validate an array with errors will
	 * be returned. The error message will be generated by the constraints
	 * which are defined in the entity getConstraints method.
	 *
	 * @param  array $values
	 * @throws \Btq\Core\Exception\MethodDoesNotExistException
	 * @return array
	 */
	public static function validate( $object, array $values, array $constraints )
	{
		$errors = array();
	
		// Build a validator and get the entity constraints from the
		// entity object.
		$validatorBuilder = new ValidatorBuilder();
		$validator = $validatorBuilder->getValidator();

		// Loop through all the passed in values. Each $value represents a
		// value of a $property on the object. 
		foreach( $values as $property => $value )
		{
			// Check if we have contraints for this property. If not, test next property.
			if( ! isset( $constraints[ $property ] ) )
			{
				continue;
			}

			// Check if the $value is a map (i.e. an array without a zero index). If so,
			// then we're dealing with a complex object into which we need to recurse into.
			if( is_array( $value ) AND ! isset( $value[0] ) )
			{
				// We're going to recurise into the sub-object but to get access to it we 
				// need to construct it's "getter" method.
				$method = "get".ucfirst( $property );

				// Check if the "getter" method exists on the and the property exists in the
				// constraints before calling it. If either doesn't exist we fail silently 
				// and skip to the next property.
				if( method_exists( $object, $method ) AND array_key_exists( $property, $constraints ) )
				{
					// The method exists so we can recurse into it. The $value represents an
					// array of property/value pairs that must be set on the sub-object.
					$results = static::validate( $object->{$method}(), $value, $constraints[ $property ] );
					
					// The $results represent the errors that were caused by the validation
					// of the sub-entity. If we have errors, then apply them to the error
					// object.
					if( ! empty( $results ) )
					{
						$errors[ $property ] = $results;
					}
				}
			}
			else
			{
				// We're dealing with a simple property. The $value represents the new
				// value that the property must be set to and we grab the contraints
				// based on the property name.
				$results = $validator->validateValue( $value, $constraints[ $property ] );

				// A property can have many constraints. E.g. an email address must be
				// a valid email address and cannot be blank. This means that the validation
				// can return multiple errors for a single field. Here we add each individual 
				// error for the validated property.
				if( $results->count() > 0 )
				{
					foreach( $results as $result )
					{
						$errors[ $property ][] = $result->getMessage();
					}
				}
			}
		}
	
		return $errors;
	}

}