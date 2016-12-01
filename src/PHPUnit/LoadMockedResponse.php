<?php

namespace BBC\iPlayerRadio\WebserviceKit\PHPUnit;

/**
 * @codeCoverageIgnore
 */
trait LoadMockedResponse
{
    /**
     * @param   string  $filename
     * @return  string
     */
    protected function loadMockedResponse($filename)
    {
        $responsesDir = __DIR__.'/../../tests/mock_responses/';
        if (!file_exists($responsesDir.$filename)) {
            throw new \InvalidArgumentException('Unknown mocked response "'.$filename.'"');
        }
        return file_get_contents($responsesDir.$filename);
    }
}
