<?php


namespace Cijber\FleaMarket;


use Cijber\FleaMarket\KeyValueStorage\Key;
use Cijber\FleaMarket\KeyValueStorage\Key\IntKey;
use Ramsey\Uuid\UuidInterface;


interface StallInterface {
    /**
     * Insert a new document into the database
     *
     * @param array $document
     *
     * @return array The document you inserted, but mutated before insertion with the id on `:id`
     */
    public function insert(array $document): array;

    /**
     * Delete a document from the database
     *
     * @param array|UuidInterface|string $docOrId Expects either an id as string or Uuid, or an array with the field `:id` with a valid uuid, this allows for you to just feed it the document
     *
     * @return array The deleted document
     */
    public function delete(array|UuidInterface|string $docOrId): array;

    /**
     * @param array                     $document The document to update
     * @param string|UuidInterface|null $id       The Id of the document to update, if null is given the field `:id` of the document is used
     *
     * @return mixed The updated document, this is just your given document with `:id` set
     */
    public function update(array $document, null|UuidInterface|string $id = null): array;

    ///**
    // * @param array                     $document The document to "upsert"
    // * @param string|UuidInterface|null $id       The Id of the document to update, if null is given the field `:id` of the document is used
    // *
    // * @return mixed The updated document, this is just your given document with `:id` set
    // */
    //public function upsert(array $document, null|UuidInterface|string $id = null): array;

    /**
     * Define a new index (btree based) for this database, this index is default sorted and allows for cheaper range look ups
     *
     * @param string     $name The name of this index, will be used for querying
     * @param string|Key $key  The indexing key to be used, this is used for encoding and comparing see Key for more info
     * @param bool       $new  If a new index should be created if one exists already for this name
     *
     * @return Index
     * @see Key
     */
    public function rangeIndex(string $name, string|Key $key = IntKey::class, bool $new = false): Index;

    /**
     * Define a new index (hashmap based) for this database, this index is unsorted but allows for cheaper direct look ups
     *
     * @param string $name The name of this index, will be used for querying
     * @param bool   $new  If a new index should be created if one exists already for this name
     *
     * @return Index
     */
    public function index(string $name, bool $new = false): Index;

    /**
     * Create a query for this database
     *
     * @return Query
     */
    public function find(): Query;

    /**
     * Return all documents in the database
     *
     * @return iterable
     */
    public function all(): iterable;

    /**
     * Run a query on this database instance
     *
     * @param Query $query
     *
     * @return iterable
     */
    public function runQuery(Query $query): iterable;

    public function close(): void;
}