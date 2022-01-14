<?php

namespace Pixie\QueryBuilder;

class Transaction extends QueryBuilderHandler
{

    /**
     * Commit the database changes
     *
     * @throws TransactionHaltException
     */
    public function commit()
    {
        $this->dbInstance->query('COMMIT');
        throw new TransactionHaltException();
    }

    /**
     * Rollback the database changes
     *
     * @throws TransactionHaltException
     */
    public function rollback()
    {
        $this->dbInstance->query('ROLLBACK');
        throw new TransactionHaltException();
    }
}
