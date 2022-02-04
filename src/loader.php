<?php



/**
 * Pixie WPDB Static Loader
 *
 * Just include this file in your theme or plugin to have Pixie loaded and ready to go
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
 * @author Glynn Quelch <glynn@pinkcrab.co.uk>
 * @license http://www.opensource.org/licenses/mit-license.html  MIT License
 * @package Gin0115\Pixie WPDB
 * @since 0.0.1
 */

// Generated code start...
if (!trait_exists(Pixie\QueryBuilder\TablePrefixer::class)) {
    require_once __DIR__ . '/QueryBuilder/TablePrefixer.php';
}
if (!interface_exists(Pixie\HasConnection::class)) {
    require_once __DIR__ . '/HasConnection.php';
}
if (!class_exists(Pixie\Binding::class)) {
    require_once __DIR__ . '/Binding.php';
}
if (!class_exists(Pixie\QueryBuilder\QueryBuilderHandler::class)) {
    require_once __DIR__ . '/QueryBuilder/QueryBuilderHandler.php';
}
if (!class_exists(Pixie\QueryBuilder\Transaction::class)) {
    require_once __DIR__ . '/QueryBuilder/Transaction.php';
}
if (!class_exists(Pixie\QueryBuilder\Raw::class)) {
    require_once __DIR__ . '/QueryBuilder/Raw.php';
}
if (!class_exists(Pixie\JSON\JsonHandler::class)) {
    require_once __DIR__ . '/JSON/JsonHandler.php';
}
if (!class_exists(Pixie\EventHandler::class)) {
    require_once __DIR__ . '/EventHandler.php';
}
if (!class_exists(Pixie\QueryBuilder\QueryObject::class)) {
    require_once __DIR__ . '/QueryBuilder/QueryObject.php';
}
if (!class_exists(Pixie\QueryBuilder\JoinBuilder::class)) {
    require_once __DIR__ . '/QueryBuilder/JoinBuilder.php';
}
if (!class_exists(Pixie\QueryBuilder\NestedCriteria::class)) {
    require_once __DIR__ . '/QueryBuilder/NestedCriteria.php';
}
if (!class_exists(Pixie\JSON\JsonSelectorHandler::class)) {
    require_once __DIR__ . '/JSON/JsonSelectorHandler.php';
}
if (!class_exists(Pixie\JSON\JsonSelector::class)) {
    require_once __DIR__ . '/JSON/JsonSelector.php';
}
if (!class_exists(Pixie\Connection::class)) {
    require_once __DIR__ . '/Connection.php';
}
if (!class_exists(Pixie\QueryBuilder\JsonQueryBuilder::class)) {
    require_once __DIR__ . '/QueryBuilder/JsonQueryBuilder.php';
}
if (!class_exists(Pixie\Hydration\Hydrator::class)) {
    require_once __DIR__ . '/Hydration/Hydrator.php';
}
if (!class_exists(Pixie\QueryBuilder\WPDBAdapter::class)) {
    require_once __DIR__ . '/QueryBuilder/WPDBAdapter.php';
}
if (!class_exists(Pixie\Exception::class)) {
    require_once __DIR__ . '/Exception.php';
}
if (!class_exists(Pixie\QueryBuilder\TransactionHaltException::class)) {
    require_once __DIR__ . '/QueryBuilder/TransactionHaltException.php';
}
if (!class_exists(Pixie\AliasFacade::class)) {
    require_once __DIR__ . '/AliasFacade.php';
}
if (!class_exists(Pixie\JSON\JsonExpressionFactory::class)) {
    require_once __DIR__ . '/JSON/JsonExpressionFactory.php';
}
// CREATED ON Fri 4th February 2022
