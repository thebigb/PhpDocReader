<?php

namespace PhpDocReader;

use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

interface PhpDocReaderInterface
{
	/**
	 * Parse the docblock of the property to get the class of the var annotation.
	 *
	 * @param ReflectionProperty $property
	 *
	 * @throws AnnotationException
	 * 
	 * @return null|string Type of the property (content of var annotation)
	 */
	public function getPropertyClass(ReflectionProperty $property);

	/**
	 * Parse the docblock of the property to get all classes declared in the var annotation.
	 *
	 * @param ReflectionProperty $property
	 *
	 * @return string[]
	 * 
	 * @throws InvalidClassException
	 */
	public function getPropertyClasses(ReflectionProperty $property);

	/**
	 * Parse the docblock of the method to get the class of the param annotation.
	 *
	 * @param ReflectionParameter $parameter
	 *
	 * @throws AnnotationException
	 * 
	 * @return null|string Type of the property (content of var annotation)
	 */
	public function getParameterClass(ReflectionParameter $parameter);

	/**
	 * Parse the docblock of the method to get all classes declared in the param annotation.
	 *
	 * @param ReflectionParameter $parameter
	 *
	 * @return string[]
	 * 
	 * @throws InvalidClassException
	 */
	public function getParameterClasses(ReflectionParameter $parameter);

	/**
	 * Parse the docblock of the method to get the class of the return annotation.
	 *
	 * @param ReflectionMethod $method
	 *
	 * @return null|string
	 * 
	 * @throws InvalidClassException
	 */
	public function getMethodReturnClass(ReflectionMethod $method);

	/**
	 * Parse the docblock of the method to get all classes declared in the return annotation.
	 *
	 * @param ReflectionMethod $method
	 *
	 * @return string[]
	 * 
	 * @throws InvalidClassException
	 */
	public function getMethodReturnClasses(ReflectionMethod $method);
}
