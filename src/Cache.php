<?php

namespace Expensify\Bedrock;

use Expensify\Bedrock\Exceptions\ConnectionFailure;

/**
 * Encapsulates the built-in Cache plugin to Bedrock.
 *
 * @see https://github.com/Expensify/Bedrock/blob/master/plugins/Cache.md
 */
class Cache extends Plugin
{
    /**
     * Store if the cache is available and functional.
     *
     * @var bool
     */
    private static $hasFailed = false;

    /**
     * Reads a named value from the cache.  Can optionally request a specific
     * version of that value, if available.
     *
     * @param string $name    Name pattern (using LIKE syntax) to read.
     * @param string $version (optional) Specific version identifier (ie, a timestamp, counter, name, etc), defaults to the latest
     *
     * @return array Containing "name" (the name matched), "rawBody" (unparsed), and "body" (JSON parsed)
     */
    public function read($name, $version = null)
    {
        $fullName = ($version ? "$name/$version" : "$name/*");
        $this->client->getLogger()->info("BedrockCache read", [
            'key' => $name,
            'version' => $version,
        ]);

        return $this->call([
            "ReadCache",
            [
                "name" => $fullName,
            ],
        ]);
    }

    /**
     * Writes a named value to the cache, overriding any value of the same
     * name.  If a version is provided, also invalidates all other versions of
     * the value.  This write is asynchronous (eg, it returns when it has been
     * successfully queued with the server, but before the write itself has
     * completed).
     *
     * @param string $name    Arbitrary string used to uniquely name this value.
     * @param mixed  $value   Raw binary data to associate with this name
     * @param string $version (optional) Version identifier (eg, a timestamp, counter, name, etc)
     *
     * @return array
     */
    public function write($name, $value, $version = null)
    {
        // If we have a version, invalidate previous versions
        $headers = [
            "Connection" => "forget",
        ];
        if ($version) {
            // Invalidate all other versions of this name before setting
            $headers["invalidateName"] = "$name/*";
            $headers["name"]           = "$name/$version";
        } else {
            // Just set this name
            $headers["name"] = "$name/";
        }

        return $this->call([
                "WriteCache",
                $headers,
                $value,
        ]);
    }

    /**
     * Call the bedrock cache methods, and handle connection error.
     *
     * @param array $parameters
     *
     * @return mixed|null
     */
    private function call(array $parameters)
    {
        if (self::$hasFailed) {
            $this->client->getLogger()->info('Skip Bedrock Cache call because we have failed before');

            return;
        }

        try {
            return call_user_func_array([$this->client, 'call'], $parameters);
        } catch (ConnectionFailure $e) {
            $this->client->getLogger()->alert('Bedrock Cache read', ['exception' => $e]);
            self::$hasFailed = true;

            return;
        }
    }
}
