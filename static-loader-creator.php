<?php

class StaticLoaderCreator
{
    /** @var string[] Array of file names */
    protected array $files = [];

    /** @var string[] Array of class names */
    protected array $classes = [];

    /** @var string Path to the SRC directory. */
    protected $routePath;

    public function __construct()
    {
        $this->routePath = __DIR__ . DIRECTORY_SEPARATOR . 'src';
    }


    public static function run(): void
    {
        $instance = new self();
        $instance->getFiles();
        $instance->getClassNames();
        $instance->writeLoader();
    }

    /**
     * Get all filenames excluding the loader.
     *
     * @return void
     */
    public function getFiles(): void
    {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->routePath)
        );

        foreach ($rii as $file) {
            if ($file->isDir() || $file->getPathname() === $this->routePath . DIRECTORY_SEPARATOR . 'loader.php') {
                continue;
            }

            $this->files[] = $file->getPathname();
        }
    }

    /**
     * Compiles the list of classnames with file paths.
     *
     * @return void
     */
    public function getClassNames(): void
    {
        foreach ($this->files as $file) {
            $classData['class'] = $this->getObjectTypeFromFile($file, T_CLASS);
            $classData['trait'] = $this->getObjectTypeFromFile($file, T_TRAIT);
            $classData['interface'] = $this->getObjectTypeFromFile($file, T_INTERFACE);
            $classData['ns'] = $this->getNamespace($file);
            $classData['file'] = str_replace($this->routePath, '', $file);
            $this->classes[$file] = $classData;
        }

        // Set all classes to be required last, allow traits to load first.
        uasort($this->classes, function ($file1, $file2) {
            return is_null($file1['class']) ? -1 : 1;
        });
    }

    /**
     * Gets the namespace from a file.
     *
     * @see https://stackoverflow.com/questions/7153000/get-class-name-from-file/44654073
     * @param string $file
     * @return void
     */
    public function getNamespace($file)
    {
        $src = file_get_contents($file);

        $tokens = token_get_all($src);
        $count = count($tokens);
        $i = 0;
        $namespace = '';
        $namespace_ok = false;
        while ($i < $count) {
            $token = $tokens[$i];
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                // Found namespace declaration
                while (++$i < $count) {
                    if ($tokens[$i] === ';') {
                        $namespace_ok = true;
                        $namespace = trim($namespace);
                        break;
                    }
                    $namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
                }
                break;
            }
            $i++;
        }
        if (!$namespace_ok) {
            return null;
        } else {
            return $namespace;
        }
    }

    /**
     * get the class name form file path using token
     *
     * @see https://stackoverflow.com/questions/7153000/get-class-name-from-file/44654073
     * @param string $filePathName
     * @return string|null
     */
    protected function getObjectTypeFromFile($filePathName, $type = T_CLASS): ?string
    {
        $php_code = file_get_contents($filePathName);

        $classes = array();
        $tokens = token_get_all($php_code);
        $count = count($tokens);
        for ($i = 2; $i < $count; $i++) {
            if (
                $tokens[$i - 2][0] == $type
                && $tokens[$i - 1][0] == T_WHITESPACE
                && $tokens[$i][0] == T_STRING
            ) {
                $class_name = $tokens[$i][1];
                $classes[] = $class_name;
            }
        }

        return array_key_exists(0, $classes) ? $classes[0] : null;
    }

    /**
     * Writes the loader.php file.
     *
     * @return void
     */
    public function writeLoader(): void
    {
        $newLine = PHP_EOL;
        $header = "<?php{$newLine}{$newLine}{$newLine}
/**
 * Pixie WPDB Static Loader
 *
 * Just include this file in your theme or plugin to have Pixie loaded and ready to go
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * \"AS IS\" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Glynn Quelch <glynn@pinkcrab.co.uk>
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @package Gin0115\Pixie WPDB
 * @since 0.0.1
 */

// Generated code start...";

        $contents = array_map(function ($file, $class) {
             return sprintf(
                 "if (!%s(%s::class)) {
    require_once __DIR__ . '%s';
}",
                 $this->getMethodFromToken($class),
                 $this->getFullTokenName($class),
                 $class['file']
             );
        }, array_keys($this->classes), $this->classes);

        $footer = sprintf("// CREATED ON %s", date('D jS F Y', time()));

        $file = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'loader.php';
        touch($file);

        file_put_contents(
            $file,
            join(PHP_EOL, [
                $header,
                join(PHP_EOL, $contents),
                $footer, ''
            ])
        );
    }

    /**
     * Gets the *_exists() method based on the token type.
     *
     * @param array $token
     * @return string
     */
    public function getMethodFromToken(array $token): string
    {
        switch (true) {
            case ! is_null($token['trait']):
                return 'trait_exists';

            case ! is_null($token['interface']):
                return 'interface_exists';

            default:
                return 'class_exists';
        }
    }

    /**
     * Returns the full (namespaced) token name
     *
     * @param array $token
     * @return string
     */
    public function getFullTokenName(array $token): string
    {
        switch (true) {
            case ! array_key_exists('ns', $token):
                return '';

            case ! is_null($token['trait']):
                return $token['ns'] . '\\' . $token['trait'];

            case ! is_null($token['interface']):
                return $token['ns'] . '\\' . $token['interface'];

            case ! is_null($token['class']):
                return $token['ns'] . '\\' . $token['class'];

            default:
                return '';
        }
    }
}

StaticLoaderCreator::run();
