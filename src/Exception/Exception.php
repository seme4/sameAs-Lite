<?php

/**
 * Custom Exceptions
 *
 * @package SameAsLite
 */

namespace SameAsLite\Exception;

/**
 * Base Exception class
 */
class Exception extends \Exception
{

    /**
     * If an exception is thrown, the Slim Framework will use this function to
     * render details (if in development mode) or present a basic error page.
     *
     * @param \Exception $e The Exception which caused the error
     */
    public static function outputException(\Exception $e)
    {
        $status = 500;
        
        // the user requested an unsupported content type
        if ($e instanceof Exception\ContentTypeException) {

            // this is a client error -> use the correct header in 4XX range
            // $this->app->response->setStatus(406); //406 Not Acceptable
            $status = 406; // TODO
            // the above does not work!
            header($_SERVER['SERVER_PROTOCOL'] . " 406 Not Acceptable");

            // TODO:
            // add the available formats in response header
            // see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.4.7
            // Unless it was a HEAD request, the response SHOULD include an entity
            // containing a list of available entity characteristics and location(s)
            // from which the user or user agent can choose the one most appropriate.
            // The entity format is specified by the media type given in the Content-Type
            // header field. Depending upon the format and the capabilities of the user agent,
            // selection of the most appropriate choice MAY be performed automatically.
            // However, this specification does not define any standard for such automatic selection.





            // $app->response->headers->set('Content-Type', 'application/json');

        }


        if ($this->app->getMode() == 'development') {

            // show details if we are in dev mode
            $op  = PHP_EOL;
            $op .= "        <h4>Request details &ndash;</h4>" . PHP_EOL;
            $op .= "        <dl class=\"dl-horizontal\">" . PHP_EOL;
            foreach ($_SERVER as $key => $value) {
                $key = strtolower($key);
                $key = str_replace(array('-', '_'), ' ', $key);
                $key = preg_replace('#^http #', '', $key);
                $key = ucwords($key);
                if (is_array($value)) {
                    $op .= "<dt>$key</dt><dd><pre>" . print_r($value, true) . "</pre></dd>" . PHP_EOL;
                } else {
                    $op .= "          <dt>$key</dt>" . PHP_EOL .
                           "          <dd>$value</dd>" . PHP_EOL;
                }
            }
            $op .= "        </dl>" . PHP_EOL;

            $msg = $e->getMessage();
            $summary = '';
            if (($p = strpos($msg, ' // ')) !== false) {
                $summary = substr($msg, $p + 4) . '</p><p>';
                $msg = substr($msg, 0, $p);
            }

            $this->outputError(
                $status,
                'Server Error',
                $summary . '<strong>' . $e->getFile() . '</strong> &nbsp; +' . $e->getLine(),
                $msg,
                "<h4>Stack trace &ndash;</h4>" . PHP_EOL .
                '        <pre class="white">' . $e->getTraceAsString() . '</pre>' .
                $op
            );

        } else {

            // show basic message
            $this->outputError(
                $status,
                'Unexpected Error',
                '</p><p>Apologies for any inconvenience, the problem has been logged and we\'ll get on to it ASAP.',
                'Whoops! An unexpected error has occured...'
            );

        }
    }

}
