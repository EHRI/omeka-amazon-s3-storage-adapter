<?php

use Aws\S3\S3Client;


class Omeka_Storage_Adapter_AmazonS3 implements Omeka_Storage_Adapter_AdapterInterface
{
    const AWS_KEY_OPTION = 'accessKeyId';
    const AWS_SECRET_KEY_OPTION = 'secretAccessKey';
    const REGION_OPTION = 'region';
    const BUCKET_OPTION = 'bucket';
    const EXPIRATION_OPTION = 'expiration';

    /**
     * @var S3Client
     */
    private $_s3;

    /**
     * @var array
     */
    private $_options;

    /**
     * Set options for the storage adapter.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->_options = $options;

        if (array_key_exists(self::AWS_KEY_OPTION, $options)
            && array_key_exists(self::AWS_SECRET_KEY_OPTION, $options)
        ) {
            // FIXME: Probably a better way of doing this...
            putenv('AWS_ACCESS_KEY_ID=' . $options[self::AWS_KEY_OPTION]);
            putenv('AWS_SECRET_ACCESS_KEY=' . $options[self::AWS_SECRET_KEY_OPTION]);
        } else {
            throw new Omeka_Storage_Exception('You must specify your AWS access key and secret key to use the AmazonS3 storage adapter.');
        }

        if (!array_key_exists(self::REGION_OPTION, $options)) {
            throw new Omeka_Storage_Exception('You must specify an S3 region name to use the AmazonS3 storage adapter.');
        }

        if (!array_key_exists(self::BUCKET_OPTION, $options)) {
            throw new Omeka_Storage_Exception('You must specify an S3 bucket name to use the AmazonS3 storage adapter.');
        }

        $this->_s3 = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => $options[self::REGION_OPTION]
        ]);
    }

    public function setUp()
    {
        // Required by interface but does nothing, for the time being.
    }

    public function canStore()
    {
        return $this->_s3->doesBucketExist($this->_getBucket());
    }


    /**
     * Move a local file to S3 storage.
     *
     * @param string $source Local filesystem path to file.
     * @param string $dest Destination path.
     */
    public function store($source, $dest)
    {
        $result = $this->_s3->putObject([
            'ACL' => $this->_getAcl(),
            'Bucket' => $this->_getBucket(),
            'SourceFile' => $source,
            'Key' => $dest
        ]);
        $objectName = $result->get('ObjectURL');
        _log("Omeka_Storage_Adapter_AmazonS3: Stored '$source' as: $objectName");
        unlink($source);
    }

    /**
     * Move a file between two "storage" locations.
     *
     * @param string $source Original stored path.
     * @param string $dest Destination stored path.
     */
    public function move($source, $dest)
    {
        $result = $this->_s3->copy(
            $this->_getBucket(),
            $source,
            $this->_getBucket(),
            $dest,
            $this->_getAcl()
        );
        $this->delete($source);
        $objectName = $result->get('ObjectURL');
        _log("Omeka_Storage_Adapter_AmazonS3: Moved '$source' to: $objectName");
    }

    /**
     * Remove a "stored" file.
     *
     * @param string $path
     */
    public function delete($path)
    {
        $this->_s3->deleteObject([
            'Bucket' => $this->_getBucket(),
            'Key' => $path
        ]);
        _log("Omeka_Storage_Adapter_AmazonS3: Deleted: $path");
    }

    /**
     * Get a URI for a "stored" file.
     *
     * @param string $path
     * @return string URI
     */
    public function getUri($path)
    {
        if ($expiration = $this->_getExpiration()) {
            $cmd = $this->_s3->getCommand('GetObject', [
                'Bucket' => $this->_getBucket(),
                'Key' => $path
            ]);
            $request = $this->_s3->createPresignedRequest($cmd, "+$expiration minutes");
            $uri = (string)$request->getUri();
            debug("Omeka_Storage_Adapter_AmazonS3: generating URI to expire in $expiration minutes: $uri");
            return $uri;
        }

        return $this->_s3->getObjectUrl($this->_getBucket(), $path);
    }

    /**
     * Return the service object being used for S3 requests.
     *
     * @return S3Client
     */
    public function getS3Service()
    {
        return $this->_s3;
    }

    /**
     * Get the AWS bucket name
     *
     * @return string
     */
    private function _getBucket()
    {
        return $this->_options[self::BUCKET_OPTION];
    }

    /**
     * Normalizes and returns the expiration time.
     *
     * Converts to integer and returns zero for all non-positive numbers.
     *
     * @return int
     */
    private function _getExpiration()
    {
        $expiration = (int)@$this->_options[self::EXPIRATION_OPTION];
        return $expiration > 0 ? $expiration : 0;
    }

    /**
     * Get the ACL string. As per the behaviour of the ZendS3 storage
     * adapter, items with an expiration are assumed to be private and
     * those without publicly readable.
     *
     * @return string
     */
    private function _getAcl()
    {
        return $this->_getExpiration() ? 'private' : 'public-read';
    }

    /**
     * Checks if a given object exists
     *
     * @param  string $object
     * @return boolean
     */
    public function isObjectAvailable($object)
    {
        $response = $this->_s3->headObject([
          'Bucket' => $this->_getBucket(),
          'Key' => $object
        ]);

        return ($response->get('@metadata')['statusCode'] ?? false == 200);
    }
}
