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

if (class_exists('PHP_CodeSniffer_CommentParser_FunctionCommentParser', true) === false) {
    $error = 'Class PHP_CodeSniffer_CommentParser_FunctionCommentParser not found';
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
 * @copyright 2014. All rights reserved.
 */
class CodeStandard_Sniffs_Commenting_FunctionCommentSniff implements PHP_CodeSniffer_Sniff
{


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_FUNCTION);

    }//end register()

    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $find = array(
                 T_COMMENT,
                 T_DOC_COMMENT,
                 T_CLASS,
                 T_FUNCTION,
                 T_OPEN_TAG,
                );

        $commentEnd = $phpcsFile->findPrevious($find, ($stackPtr - 1));

        if ($commentEnd === false) {
            return;
        }

        // Find the first doc comment.
        $commentStart   = ($phpcsFile->findPrevious(T_DOC_COMMENT, ($commentEnd - 1), null, true) + 1);
        $commentString  = $phpcsFile->getTokensAsString($commentStart, ($commentEnd - $commentStart + 1));

        try {
            $this->commentParser = new PHP_CodeSniffer_CommentParser_FunctionCommentParser($commentString, $phpcsFile);
            $this->commentParser->parse();
        } catch (PHP_CodeSniffer_CommentParser_ParserException $e) {
            $line = ($e->getLineWithinComment() + $commentStart);
            $phpcsFile->addError($e->getMessage(), $line, 'FailedParse');
            return;
        }

        //  determine whether the function actually contains a return statement
        $hasReturn = false;
        $tokens = $phpcsFile->getTokens();
        if (isset($tokens[$stackPtr]['scope_closer']) === true) {
            $endToken = $tokens[$stackPtr]['scope_closer'];

            $tokens = $phpcsFile->getTokens();
            for ($returnToken = $stackPtr; $returnToken < $endToken; $returnToken++) {
                if ($tokens[$returnToken]['code'] === T_CLOSURE) {
                    $returnToken = $tokens[$returnToken]['scope_closer'];
                    continue;
                }

                if ($tokens[$returnToken]['code'] === T_RETURN) {
                    $hasReturn = true;
                }
            }
        }

        //  get method name
        $data     = array($phpcsFile->getDeclarationName($stackPtr));

        //  parse the function doc block
        $comment = $this->commentParser->getComment();

        //  try to parse the return tag from within comment
        $return  = $this->commentParser->getReturn();
        if ($return === null) {
            $content  = null;
            $errorPos = $commentEnd;
        } else {
            $content = trim($return->getRawContent());
            $errorPos = ($commentStart + $return->getLine());
        }

        if ($content === 'void') {
            $error = '@return void is unnecessary and against best practice in PHP';
            $phpcsFile->addError($error, $errorPos, 'InvalidReturnVoid', $data);
        } else if (!$hasReturn && $return !== null) {
            $error = 'Function does not return anything, please remove @return tag';
            $phpcsFile->addError($error, $errorPos, 'UnneccessaryReturnTag', $data);
        } else if ($hasReturn && $return === null) {
            $error = 'Missing @return tag in function: %s';
            $phpcsFile->addError($error, $errorPos, 'MissingReturnTag', $data);
        }

    }//end processReturn()


    /**
     * Check whether the current function contains a return statement.
     *
     * @return boolean A boolean value representing the presence
     *                 of at least one return statement
     */
    private function containsReturnStatement()
    {


    }//end containsReturnStatement

}//end class

?>
