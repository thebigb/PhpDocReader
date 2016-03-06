<?php

namespace PhpDocReader;

use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class PhpDocReader implements PhpDocReaderInterface
{
	const TAG_RETURN = 'return';
	const TAG_PROPERTY = 'var';
	const TAG_PARAMETER = 'param';

	/**
	 * Holds an instance of the PhpParser class
	 *
	 * @var PhpParser $parser
	 */
	private $parser;

	/**
	 * @param bool $ignorePhpDocErrors
	 */
	public function __construct($ignorePhpDocErrors = false)
	{
		$this->parser = new PhpParser($ignorePhpDocErrors);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPropertyClass(ReflectionProperty $property)
	{
		return $this->parseTagType($property, self::TAG_PROPERTY);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getPropertyClasses(ReflectionProperty $property)
	{
		return $this->parseTagTypes($property, self::TAG_PROPERTY);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getParameterClass(ReflectionParameter $parameter)
	{
		return $this->parseTagType($parameter, self::TAG_PARAMETER);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getParameterClasses(ReflectionParameter $parameter)
	{
		return $this->parseTagTypes($parameter, self::TAG_PARAMETER);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMethodReturnClass(ReflectionMethod $method)
	{
		return $this->parseTagType($method, self::TAG_RETURN);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMethodReturnClasses(ReflectionMethod $method)
	{
		return $this->parseTagTypes($method, self::TAG_RETURN);
	}

	/**
	 * Detects and resolves the type declared by the specified $tagName on the provided $member.
	 *
	 * @param ReflectionProperty|ReflectionMethod|ReflectionParameter $member
	 * @param string $tagName
	 *
	 * @return null|string Resolved type
	 *
	 * @throws CannotResolveException
	 * @throws InvalidClassException
	 */
	private function parseTagType($member, $tagName)
	{
		$class = $member->getDeclaringClass();

		$typeDeclaration = $this->getTag($member, $tagName);

		$resolvedType = $this->parser->resolveType($typeDeclaration, $class, $member);

		if ($resolvedType === null)
		{
			return null;
		}

		// Validate resolved type
		if (!class_exists($resolvedType) && !interface_exists($resolvedType))
		{
			throw new InvalidClassException($resolvedType, $member);
		}

		// Strip preceding backslash to make a valid FQN
		return ltrim($resolvedType, '\\');
	}

	/**
	 * Detects and resolves the types declared by the specified $tagName on the provided $member.
	 *
	 * @param ReflectionProperty|ReflectionMethod|ReflectionParameter $member
	 * @param string $tagName
	 *
	 * @return string[] Array of resolved types
	 *
	 * @throws CannotResolveException
	 * @throws InvalidClassException
	 */
	private function parseTagTypes($member, $tagName)
	{
		$class = $member->getDeclaringClass();

		$typeDeclaration = $this->getTag($member, $tagName);

		// Split up type declaration
		$types = explode('|', $typeDeclaration);

		$result = array();
		
		foreach ($types as $type)
		{
			$resolvedType = $this->parser->resolveType($type, $class, $member);

			// Skip if null
			if ($resolvedType === null)
			{
				continue;
			}

			// Validate resolved type
			if (!class_exists($resolvedType) && !interface_exists($resolvedType))
			{
				throw new InvalidClassException($resolvedType, $member);
			}

			// Strip preceding backslash to make a valid FQN
			$result[] = ltrim($resolvedType, '\\');
		}
		
		return $result;
	}

	/**
	 * Retrieves the type declaration from the first tag of the specified $tagName that are relevant to the provided
	 * $member.
	 *
	 * @param ReflectionProperty|ReflectionMethod|ReflectionParameter $member
	 * @param string $tagName
	 *
	 * @return null|string Type declaration
	 */
	private function getTag($member, $tagName)
	{
		$tags = $this->getTags($member, $tagName);

		return $tags ? $tags[0] : null;
	}

	/**
	 * Retrieves type declarations from all tags of the specified $tagName that are relevant to the provided $member.
	 *
	 * @param ReflectionProperty|ReflectionMethod|ReflectionParameter $member
	 * @param string $tagName
	 *
	 * @return string[] An array of type declarations
	 */
	private function getTags($member, $tagName)
	{
		if ($member instanceof ReflectionParameter)
		{
			// Look for a tag for a specific variable
			$expression = '/@' . preg_quote($tagName) . '\s+([^\s]+)\s+\$' . preg_quote($member->name) . '/';
			$docBlock = $member->getDeclaringFunction()->getDocComment();
		}
		else
		{
			// Generic tag search
			$expression = '/@' . preg_quote($tagName) . '\s+([^\s]+)/';
			$docBlock = $member->getDocComment();
		}
		return preg_match_all($expression, $docBlock, $matches) ? $matches[1] : null;
	}
}
