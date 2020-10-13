<?php

namespace Codeception\Module;

use Codeception\Configuration;
use Codeception\Module as CodeceptionModule;
use Codeception\TestInterface;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

/**
 * Connects to [Elasticsearch](https://www.elastic.co/) using elasticsearch/elasticsearch official php client for
 * Elasticsearch.
 *
 * Can cleanup by delete all or listed in config indexes after each test or test suite run.
 *
 * ## Config
 *
 * * hosts *required* - elasticsearch hosts
 * * snapshotPath - path to snapshot
 * * snapshotName - snapshot name
 * * compressedSnapshot: true - is snapshot compressed
 * * populateBeforeTest: false - whether the snapshot should be loaded before the test suite is started
 * * populateBeforeSuite: false - whether the snapshot should be reloaded before each test
 * * cleanup: false - delete indexes from list [indexes] or all (if indexes null) after after each test or test suite
 * finished
 * * indexes: null - list of indexes to delete after after each test or test suite finished.
 *
 * ### Example (`acceptance.suite.yml`)
 *
 * ```yaml
 *    modules:
 *        - Elasticsearch:
 *            hosts:
 *              - host: 'localhost'
 *                port: 9200
 *                user: 'elastic'
 *                pass: ''
 *            snapshotPath: 'tests/_data/elasticsearch'
 *            snapshotName: 'snapshot_name'
 *            compressedSnapshot: true
 *            populateBeforeTest: true
 *            populateBeforeSuite: true
 *            cleanup: true
 *            indexes:
 *              - index1
 *              - index2
 * ```
 *
 * Be sure you don't use the production server to connect.
 *
 * ## Public Properties
 *
 * * **elasticsearchClient** - instance of Elasticsearch\Client
 *
 */
class Elasticsearch extends CodeceptionModule
{
    /**
     * @var Client
     */
    public $elasticsearchClient;

    protected $config = [
        'hosts'               => [
            [
                'host' => 'localhost',
                'port' => 9200,
                'user' => 'elastic',
                'pass' => ''
            ]
        ],
        'snapshotPath'        => null,
        'snapshotName'        => null,
        'compressedSnapshot'  => true,
        'populateBeforeTest'  => false,
        'populateBeforeSuite' => false,
        'cleanup'             => false,
        'indexes'             => null
    ];

    protected $requiredFields = ['hosts'];

    /*
     * Connect to elasticsearch hosts
     */
    protected function connect(): void
    {
        $this->elasticsearchClient = ClientBuilder::create()->setHosts($this->config['hosts'])->build();
    }

    /*
     * Delete indexes (list from config[indexes] or all if config[indexes] is null
     */
    protected function cleanup(): void
    {
        if ($this->_getConfig('cleanup')) {
            if (is_array($this->_getConfig('indexes'))) {
                foreach ($this->_getConfig('indexes') as $index) {
                    $this->elasticsearchClient->indices()->delete(['index' => $index]);
                }
            } else {
                $this->elasticsearchClient->indices()->delete(['index' => '*']);
            }
        }
    }

    /*
     * Load snapshot from file
     */
    protected function populate(): void
    {
        // create repo
        $repoParams = [
            'repository' => 'codeception',
            'body'       => [
                'type'     => 'fs',
                'settings' => [
                    'location' => Configuration::projectDir() . $this->_getConfig('snapshotPath'),
                    'compress' => $this->_getConfig('compressedSnapshot')
                ]
            ]
        ];
        $this->elasticsearchClient->snapshot()->createRepository($repoParams);

        // restore snapshot and wait reindex is complete
        $restoreParams = [
            'repository'          => 'codeception',
            'snapshot'            => $this->_getConfig('snapshotName'),
            'wait_for_completion' => true,
        ];
        $this->elasticsearchClient->snapshot()->restore($restoreParams);

        // delete repo
        $repoParams = [
            'repository' => 'codeception'
        ];
        $this->elasticsearchClient->snapshot()->deleteRepository($repoParams);
    }

    public function _before(TestInterface $test): void
    {
        if ($this->_getConfig('populateBeforeTest')) {
            $this->populate();
        }
    }

    public function _after(TestInterface $test): void
    {
        if ($this->_getConfig('populateBeforeTest')) {
            $this->cleanup();
        }
    }

    public function _beforeSuite($settings = []): void
    {
        $this->connect();
        if ($this->_getConfig('populateBeforeSuite') && !$this->_getConfig('populateBeforeTest')) {
            $this->populate();
        }
    }

    public function _afterSuite(): void
    {
        if ($this->_getConfig('populateBeforeSuite') && !$this->_getConfig('populateBeforeTest')) {
            $this->cleanup();
        }
    }

    /**
     * Asserts that a document with the given id exists in the index. Provide index name and document id.
     *
     * @param string     $index
     * @param string|int $id
     */
    public function seeDocumentInElasticsearch($index, $id): void
    {
        $this->assertTrue(
            $this->elasticsearchClient->exists(
                [
                    'index' => $index,
                    'id'    => $id
                ]
            ),
            'No matching document found for by id ' . $id . ' in index ' . $index
        );
    }

    /**
     * Asserts there is no document with the given id exists in the index. Provide index name and document id.
     *
     * @param string     $index
     * @param string|int $id
     */
    public function dontSeeDocumentInElasticsearch($index, $id): void
    {
        $this->assertFalse(
            $this->elasticsearchClient->exists(
                [
                    'index' => $index,
                    'id'    => $id
                ]
            ),
            'No matching document found for by id ' . $id . ' in index ' . $index
        );
    }

    /**
     * Returns response of get document
     *
     * @param string     $index
     * @param string|int $id
     *
     * @return array
     */
    public function grabDocumentFromElasticsearch($index, $id): array
    {
        return $this->elasticsearchClient->get(
            [
                'index' => $index,
                'id'    => $id
            ]
        );
    }

    /**
     * Inserts document into an index
     *
     * @param string     $index
     * @param string|int $id
     * @param array      $body
     *
     * @return array
     */
    public function haveInElasticsearch($index, $id, $body): array
    {
        $response = $this->elasticsearchClient->index(
            [
                'index' => $index,
                'id'    => $id,
                'body'  => $body
            ]
        );

        $this->elasticsearchClient->indices()->refresh();

        return $response;
    }
}
