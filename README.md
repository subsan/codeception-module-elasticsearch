# Elasticsearch module for Codeception

Connects to [Elasticsearch](https://www.elastic.co/) using [elasticsearch/elasticsearch](https://github.com/elastic/elasticsearch-php) official php client.

Can cleanup by delete all or listed in config indexes after each test or test suite run.
Can restore snapshot from fs before each test or test suite.

## Installation
### Composer
```
composer require --dev "subsan/codeception-module-elasticsearch"
```
### Elasticsearch config
If You need to use snapshots You need add to elasticsearch config path to snapshots

elasticsearch.yml:
```
path.repo: ["/PATH_TO_YOUR_PROJECT/tests/_data/elasticsearch/"]
```

## Usage

### Config
* hosts *required* - elasticsearch hosts
* snapshotPath - path to snapshot
* snapshotName - snapshot name
* compressedSnapshot: true - is snapshot compressed
* populateBeforeTest: false - whether the snapshot should be loaded before the test suite is started
* populateBeforeSuite: false - whether the snapshot should be reloaded before each test
* cleanup: false - delete indexes from list [indexes] or all (if indexes null) after after each test or test suite finished
* indexes: null - list of indexes to delete after after each test or test suite finished.

### Example (`acceptance.suite.yml`)
```yaml
   modules:
        - Elasticsearch:
            hosts:
              - host: 'localhost'
                port: 9200
                user: 'elastic'
                pass: ''
            snapshotPath: 'tests/_data/elasticsearch'
            snapshotName: 'snapshot_name'
            compressedSnapshot: true
            populateBeforeTest: true
            populateBeforeSuite: true
            cleanup: true
            indexes:
              - index1
              - index2
```

### Create snapshot example:
```php
$hosts = \Jam\Core\Core::getInstance()->config()->elasticsearch()::[YOUR_HOSTS];
$raw   = \Elasticsearch\ClientBuilder::create()->setHosts($hosts)->build();

// create repo
$repoParams = [
    'repository' => 'codeception',
    'body'       => [
        'type'     => 'fs',
        'settings' => [
            'location' => '/PATH_TO_YOUR_PROJECT/tests/_data/elasticsearch',
            'compress' => true
        ]
    ]
];
$raw->snapshot()->createRepository($repoParams);

$restoreParams = [
    'repository'          => 'codeception',
    'snapshot'            => 'snapshotName',
    'wait_for_completion' => true,
    'body'                => [
        "indices"              => "INDEX_1,INDEX_2",
        "include_global_state" => false
    ]
];
$raw->snapshot()->create($restoreParams);
```

### Public Properties
* **elasticsearchClient** - instance of Elasticsearch\Client

### Actions
#### seeDocumentInElasticsearch
Asserts that a document with the given id exists in the index. Provide index name and document id.
```php
$I->seeDocumentInElasticsearch('testIndex', 111);
```

 * `param string` $index
 * `param string|id` $id

#### dontSeeDocumentInElasticsearch
Effect is opposite to ->seeDocumentInElasticsearch
Asserts there is no document with the given id exists in the index. Provide index name and document id.
```php
$I->dontSeeDocumentInElasticsearch('testIndex', 222);
```

 * `param string` $index
 * `param string|id` $id

#### grabDocumentFromElasticsearch
Returns response of get document [function](https://github.com/elastic/elasticsearch-php#get-a-document)
```php
$response = $I->grabDocumentFromElasticsearch('testIndex', 111);
print_r($response);
```

The response contains some metadata (index, version, etc.) as well as a _source field, which is the original document that you sent to Elasticsearch.
```php
Array
(
    [_index] => testIndex
    [_type] => _doc
    [_id] => 111
    [_version] => 1
    [_seq_no] => 4205
    [_primary_term] => 1
    [found] => 1
    [_source] => Array
        (
            [testField] => abc
        )
```

 * `param string` $index
 * `param string|id` $id

 * `return array` $response

#### haveInElasticsearch
Inserts document into an index
```php
$item = [
    'testField' => 'abc'
];
$I->haveInElasticsearch('testIndex', 222, $item);
```

 * `param string` $index
 * `param string|id` $id
 * `param array` $body

 * `return array` $response