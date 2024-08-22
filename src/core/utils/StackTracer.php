<?php

namespace core\utils;

class StackTracer
{

  public static function PrintStackTrace($limit = 10, $includeSelf = false): void
  {
    // Get the stack trace limited to the specified number of calls
    $offset = $includeSelf ? 0 : 1;
    $stackTrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit + $offset);

    // Print the stack trace in a human-readable format
    echo "Stack trace (last $limit function calls):\n";

    foreach ($stackTrace as $index => $trace) {
      if (!$includeSelf && $index == 0) continue;
      $index -= $offset;
      echo "#$index ";

      if (isset($trace['class'])) echo $trace['class'] . " ";

      if (isset($trace['line'])) echo "Line " . $trace['line'] . ": ";

      if (isset($trace['function'])) {
        echo $trace['function'];
        if (isset($trace['args'])) {
          echo "(" . self::formatArgs($trace['args']) . ")";
        }
      }

      echo "\n";
    }
  }

  private static function formatArgs($args): string
  {
    $formattedArgs = [];
    foreach ($args as $arg) {
      if (is_object($arg)) {
        $formattedArgs[] = get_class($arg) . '(Object)';
      } elseif (is_array($arg)) {
        $formattedArgs[] = 'Array(' . count($arg) . ')';
      } elseif (is_null($arg)) {
        $formattedArgs[] = 'NULL';
      } elseif (is_bool($arg)) {
        $formattedArgs[] = $arg ? 'true' : 'false';
      } elseif (is_string($arg)) {
        $formattedArgs[] = '"' . $arg . '"';
      } else {
        $formattedArgs[] = $arg;
      }
    }
    return implode(', ', $formattedArgs);
  }

}