<?php
/**
 * Parses and verifies the <tt>@return</tt> doc comments for functions.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Ian Millard <ian@ianmillard.com>
 * @copyright 2014. All rights reserved.
 */

if (class_exists('Squiz_Sniffs_Commenting_FunctionCommentSniff', true) === false) {
    $error = 'Class Squiz_Sniffs_Commenting_FunctionCommentSniff not found';
    throw new PHP_CodeSniffer_Exception($error);
}

/**
 * Parses and verifies the <tt>@return</tt> doc comment for functions.
 *
 * If a function contains <tt>return</tt> statement(s) then this sniffer
 * will ensure that an appropriate <tt>@return</tt> doc comment exists.
 * If the function does not return anything, then there must be no
 * <tt>@return</tt> in the doc comment.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Ian Millard <ian@ianmillard.com>
 */
class CodeStandard_Sniffs_Commenting_FunctionCommentSniff extends Squiz_Sniffs_Commenting_FunctionCommentSniff
{

    /**
     * Process the return comment of this function comment.
     *
     * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
     * @param int                  $stackPtr     The position of the current token
     *                                           in the stack passed in $tokens.
     * @param int                  $commentStart The position in the stack where the comment started.
     *
     * @return void
     */
    protected function processReturn(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
    {
        $tokens = $phpcsFile->getTokens();

        // Skip constructor and destructor.
        $className = '';
        foreach ($tokens[$stackPtr]['conditions'] as $condPtr => $condition) {
            if ($condition === T_CLASS || $condition === T_INTERFACE) {
                $className = $phpcsFile->getDeclarationName($condPtr);
                $className = strtolower(ltrim($className, '_'));
            }
        }

        $methodName      = $phpcsFile->getDeclarationName($stackPtr);
        $isSpecialMethod = ($methodName === '__construct' || $methodName === '__destruct');
        if ($methodName !== '_') {
            $methodName = strtolower(ltrim($methodName, '_'));
        }

        $return = null;
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@return') {
                if ($return !== null) {
                    $error = 'Only 1 @return tag is allowed in a function comment';
                    $phpcsFile->addError($error, $tag, 'DuplicateReturn');
                    return;
                }

                $return = $tag;
            }
        }

        //  find out if this function actually returns anything
        $functionHasReturn = false;
        if (isset($tokens[$stackPtr]['scope_closer']) === true) {
            $endToken = $tokens[$stackPtr]['scope_closer'];
            for ($returnToken = $stackPtr; $returnToken < $endToken; $returnToken++) {
                if ($tokens[$returnToken]['code'] === T_CLOSURE) {
                    $returnToken = $tokens[$returnToken]['scope_closer'];
                    continue;
                }

                if ($tokens[$returnToken]['code'] === T_RETURN) {
                    $functionHasReturn = true;
                    break;
                }
            }
        }

        if ($isSpecialMethod === false && $methodName !== $className) {
            if ($return !== null) {
                $content = $tokens[($return + 2)]['content'];
                if (empty($content) === true || $tokens[($return + 2)]['code'] !== T_DOC_COMMENT_STRING) {

                    if ($functionHasReturn) {
                        $error = 'Return type missing for @return tag in function comment';
                        $phpcsFile->addError($error, $return, 'MissingReturnType');
                    } else {
                        $error = 'Invalid @return tag, function does not return anything';
                        $phpcsFile->addError($error, $return, 'MissingReturnType');
                    }
                } else {
                    // Check return type (can be multiple, separated by '|').
                    $typeNames      = explode('|', $content);
                    $suggestedNames = array();
                    foreach ($typeNames as $i => $typeName) {
                        $suggestedName = PHP_CodeSniffer::suggestType($typeName);
                        if (in_array($suggestedName, $suggestedNames) === false) {
                            $suggestedNames[] = $suggestedName;
                        }
                    }

                    $suggestedType = implode('|', $suggestedNames);
                    if ($content !== $suggestedType) {
                        $error = 'Function return type "%s" is invalid';
                        $error = 'Expected "%s" but found "%s" for function return type';
                        $data  = array(
                                  $suggestedType,
                                  $content,
                                 );
                        $phpcsFile->addError($error, $return, 'InvalidReturn', $data);
                    }

                    // If the return type is void, make sure there is
                    // no return statement in the function.
                    if ($content === 'void') {
                        if (isset($tokens[$stackPtr]['scope_closer']) === true) {
                            $endToken = $tokens[$stackPtr]['scope_closer'];
                            for ($returnToken = $stackPtr; $returnToken < $endToken; $returnToken++) {
                                if ($tokens[$returnToken]['code'] === T_CLOSURE) {
                                    $returnToken = $tokens[$returnToken]['scope_closer'];
                                    continue;
                                }

                                if ($tokens[$returnToken]['code'] === T_RETURN) {
                                    break;
                                }
                            }

                            if ($returnToken !== $endToken) {
                                // If the function is not returning anything, just
                                // exiting, then there is no problem.
                                $semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
                                if ($tokens[$semicolon]['code'] !== T_SEMICOLON) {
                                    $error = 'Function return type is void, but function contains return statement';
                                    $phpcsFile->addError($error, $return, 'InvalidReturnVoid');
                                }
                            }
                        }//end if
                    } else if ($content !== 'mixed') {
                        // If return type is not void, there needs to be a return statement
                        // somewhere in the function that returns something.
                        if (isset($tokens[$stackPtr]['scope_closer']) === true) {
                            $endToken    = $tokens[$stackPtr]['scope_closer'];
                            $returnToken = $phpcsFile->findNext(T_RETURN, $stackPtr, $endToken);
                            if ($returnToken === false) {
                                $error = 'Invalid @return tag, function has no return statement';
                                $phpcsFile->addError($error, $return, 'InvalidNoReturn');
                            } else {
                                $semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
                                if ($tokens[$semicolon]['code'] === T_SEMICOLON) {
                                    $error = 'Superflous or Invalid @return tag, function returns void';
                                    $phpcsFile->addError($error, $returnToken, 'InvalidReturnNotVoid');
                                }
                            }
                        }
                    }//end if
                }//end if
            } else if ($functionHasReturn) {
                $content = $tokens[($return + 2)]['content'];
                if (trim($content) != '') {
                    $error = 'Missing @return tag in function comment';
                    $phpcsFile->addError($error, $tokens[$commentStart]['comment_closer'], 'MissingReturn');
                }
            }//end if
        } else {
            // No return tag for constructor and destructor.
            if ($return !== null) {
                $error = '@return tag is not required for constructor and destructor';
                $phpcsFile->addError($error, $return, 'ReturnNotRequired');
            }
        }//end if

    }//end processReturn()

}//end class

?>
