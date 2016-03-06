<?php

namespace PhpDocReader;

use Doctrine\Common\Annotations\TokenParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;
use SplFileObject;

class PhpParser
{
	/**
	 * Enable or disable throwing errors when PhpDoc Errors occur (when parsing annotations).
	 *
	 * @var bool $ignorePhpDocErrors
	 */
	private $ignorePhpDocErrors;

	/**
	 * Cache of parsed use statements indexed by class name.
	 * 
	 * @var array $cachedUseStatements
	 */
	private $cachedUseStatements = [];
	
	/**
	 * List of types that do not exist as classes or interfaces.
	 *
	 * @var array $ignoredTypes
	 */
	private $ignoredTypes = [
		'bool',
		'boolean',
		'string',
		'int',
		'integer',
		'float',
		'double',
		'array',
		'object',
		'callable',
		'resource',
	];

	/**
	 * @param bool $ignorePhpDocErrors
	 */
	public function __construct($ignorePhpDocErrors)
	{
		$this->ignorePhpDocErrors = $ignorePhpDocErrors;
	}

	/**
	 * @param ReflectionClass $class
	 *
	 * @return array A list with use statements in the form (Alias => FQN).
	 */
	public function parseUseStatements(ReflectionClass $class)
	{
		// Verify cache
		if (array_key_exists($class->name, $this->cachedUseStatements))
		{
			return $this->cachedUseStatements[$class->name];
		}
		
		if ($class->getFileName() === false)
		{
			return [];
		}

		// Read up to the class definition to retrieve the part containing the use statements
		$content = $this->readFileToLine($class->getFileName(), $class->getStartLine());

		if ($content === null)
		{
			return [];
		}

		$namespace = preg_quote($class->getNamespaceName());
		$content = preg_replace('/^.*?(\bnamespace\s+' . $namespace . '\s*[;{].*)$/s', '\\1', $content);

		$tokenizer = new TokenParser('<?php ' . $content);

		// Parse use statements
		$statements = $tokenizer->parseUseStatements($class->getNamespaceName());
		
		// Cache result for future calls
		$this->cachedUseStatements[$class->name] = $statements;
		
		return $statements;
	}

	/**
	 * Attempts to resolve the FQN of the provided $type based on the $class and $member context.
	 *
	 * @param string $type
	 * @param ReflectionClass $class
	 * @param Reflector $member
	 * @return null|string Fully qualified name of the type, or null if it could not be resolved
	 *
	 * @throws CannotResolveException
	 */
	public function resolveType($type, ReflectionClass $class, Reflector $member)
	{
		// Type hint?
		if ($member instanceof ReflectionParameter && $member->getClass() !== null)
		{
			return $member->getClass()->name;
		}

		if (!$this->isSupportedType($type))
		{
			return null;
		}

		if ($this->isFullyQualified($type))
		{
			return $type;
		}

		// Split alias from postfix
		list($alias, $postfix) = array_pad(explode('\\', $type, 2), 2, null);
		$alias = strtolower($alias);

		// Retrieve "use" statements
		$uses = $this->parseUseStatements($class);

		// Imported class?
		if (array_key_exists($alias, $uses))
		{
			return $postfix === null
				? $uses[$alias]
				: $uses[$alias] . '\\' . $postfix
			;
		}

		// In same namespace as class?
		if ($this->classExists($class->getNamespaceName() . '\\' . $type))
		{
			return $class->getNamespaceName() . '\\' . $type;
		}

		// No namespace?
		if ($this->classExists($type))
		{
			return $type;
		}

		return $this->tryResolveFqnInTraits($type, $class, $member);
	}

	/**
	 * Attempts to resolve the FQN of the provided $type based on the $class and $member context, specifically searching
	 * through the traits that are used by the provided $class.
	 *
	 * @param string $type
	 * @param ReflectionClass $class
	 * @param Reflector $member
	 *
	 * @return null|string Fully qualified name of the type, or null if it could not be resolved
	 *
	 * @throws CannotResolveException
	 */
	private function tryResolveFqnInTraits($type, ReflectionClass $class, Reflector $member)
	{
		/** @var ReflectionClass[] $traits */
		$traits = [];

		// Get traits for the class and its parents
		while ($class)
		{
			$traits = array_merge($traits, $class->getTraits());
			$class = $class->getParentClass();
		}

		foreach ($traits as $trait)
		{
			// Eliminate traits that don't have the property/method/parameter
			if ($member instanceof ReflectionProperty && !$trait->hasProperty($member->name))
			{
				continue;
			}
			elseif ($member instanceof ReflectionMethod && !$trait->hasMethod($member->name))
			{
				continue;
			}
			elseif ($member instanceof ReflectionParameter && !$trait->hasMethod($member->getDeclaringFunction()->name))
			{
				continue;
			}

			// Run the resolver again with the ReflectionClass instance for the trait
			$resolvedType = $this->resolveType($type, $trait, $member);

			if ($resolvedType)
			{
				return $resolvedType;
			}
		}

		if ($this->ignorePhpDocErrors)
		{
			return null;
		}

		throw new CannotResolveException($type, $member);
	}

	/**
	 * Gets the content of the file right up to the given line number.
	 *
	 * Copied from doctrine/annotations package:
	 * @link https://raw.githubusercontent.com/doctrine/annotations/master/lib/Doctrine/Common/Annotations/PhpParser.php
	 *
	 * @param string $filename The name of the file to load.
	 * @param integer $lineNumber The number of lines to read from file.
	 *
	 * @return string The content of the file.
	 */
	private function readFileToLine($filename, $lineNumber)
	{
		if (!is_file($filename))
		{
			return null;
		}

		$content = '';
		$currentLine = 0;
		$file = new SplFileObject($filename);

		while (!$file->eof())
		{
			if ($currentLine++ == $lineNumber)
			{
				break;
			}

			$content .= $file->fgets();
		}

		return $content;
	}

	/**
	 * Determines whether the provided name is fully qualified.
	 *
	 * @param string $name
	 *
	 * @return bool Is the name fully qualified?
	 */
	private function isFullyQualified($name)
	{
		return $name[0] === '\\';
	}

	/**
	 * Determines whether the provided type is supported by the resolver.
	 *
	 * @param string $type
	 *
	 * @return bool Is the type supported?
	 */
	private function isSupportedType($type)
	{
		return !in_array($type, $this->ignoredTypes)        // Exclude primitive types
		    && preg_match('/^[a-zA-Z0-9\\\\_]+$/', $type);  // Exclude types containing special characters ([], <> ...)
	}

	/**
	 * Determines whether the provided class exists as a class or interface.
	 *
	 * @param string $class
	 *
	 * @return bool
	 */
	private function classExists($class)
	{
		return class_exists($class) || interface_exists($class);
	}
}
