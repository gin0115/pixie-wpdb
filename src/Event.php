<?php

declare(strict_types=1);

/**
 * Event Model
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
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
 * @author  Glynn Quelch <glynn@pinkcrab.co.uk>
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 *
 * @since   0.0.1
 */

namespace Pixie;

use Closure;

class Event
{
    /**
     * Events
     */
    public const BEFORE_SELECT = 'before-select';
    public const AFTER_SELECT  = 'after-select';
    public const BEFORE_INSERT = 'before-insert';
    public const AFTER_INSERT  = 'after-insert';
    public const BEFORE_UPDATE = 'before-update';
    public const AFTER_UPDATE  = 'after-update';
    public const BEFORE_DELETE = 'before-delete';
    public const AFTER_DELETE  = 'after-delete';

    /**
     * Sentinel table that matches any table.
     */
    public const ANY_TABLE = ':any';

    /**
     * The event name, e.g. Event::BEFORE_SELECT.
     *
     * @var string
     */
    protected $name;

    /**
     * The table the event is bound to, or ':any'.
     *
     * @var string
     */
    protected $table;

    /**
     * The handler invoked when the event fires.
     *
     * @var Closure
     */
    protected $action;

    /**
     * @param string      $name   the event name
     * @param string|null $table  the table to bind to (null/':any' for any table)
     * @param Closure     $action the handler to run
     */
    public function __construct(string $name, ?string $table, Closure $action)
    {
        $this->name   = $name;
        $this->table  = $table ?? self::ANY_TABLE;
        $this->action = $action;
    }

    /**
     * Lazy static constructor for an event of any name.
     *
     * @param string      $name
     * @param string|null $table
     * @param Closure     $action
     */
    public static function on(string $name, ?string $table, Closure $action): self
    {
        return new self($name, $table, $action);
    }

    public static function beforeSelect(?string $table, Closure $action): self
    {
        return new self(self::BEFORE_SELECT, $table, $action);
    }

    public static function afterSelect(?string $table, Closure $action): self
    {
        return new self(self::AFTER_SELECT, $table, $action);
    }

    public static function beforeInsert(?string $table, Closure $action): self
    {
        return new self(self::BEFORE_INSERT, $table, $action);
    }

    public static function afterInsert(?string $table, Closure $action): self
    {
        return new self(self::AFTER_INSERT, $table, $action);
    }

    public static function beforeUpdate(?string $table, Closure $action): self
    {
        return new self(self::BEFORE_UPDATE, $table, $action);
    }

    public static function afterUpdate(?string $table, Closure $action): self
    {
        return new self(self::AFTER_UPDATE, $table, $action);
    }

    public static function beforeDelete(?string $table, Closure $action): self
    {
        return new self(self::BEFORE_DELETE, $table, $action);
    }

    public static function afterDelete(?string $table, Closure $action): self
    {
        return new self(self::AFTER_DELETE, $table, $action);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getAction(): Closure
    {
        return $this->action;
    }
}
