<?php

namespace App\Mca;

use App\Mca;
use App\Support\HashList;

class McaFile extends \SplFileInfo
{
    private array $hashes = [];

    public function __construct(string $filename)
    {
        parent::__construct($filename);

        if (! $this->isFile() || $this->isDir())
            throw new \RuntimeException('Invalid file reference provided: '.$filename);
    }

    public function getHash($algo): bool|string
    {
        if (isset($this->hashes[$algo])) return $this->hashes[$algo];

        $hash = hash_file($algo, $this->getRealPath());
        $this->hashes[$algo] = $hash;

        return $hash;
    }

    /**
     * Make and generate hash list
     *
     * @param array $knownHashes A key value array with already known hashes for the file to save compute time.
     * @return HashList
     */
    public function makeHashList(array $knownHashes = []): HashList
    {
        $list = new HashList($knownHashes);

        foreach (Mca::FILE_HASHES_ALGOS as $algo) {
            if (! $list->has($algo)) {
                $list->set($algo, $this->getHash($algo));
            }
        }

        return $list;
    }

    /**
     * Compare provided hash and actual hash.
     *
     * @param string $algo
     * @param string $hash
     * @return bool
     */
    public function verifyHash(string $algo, string $hash): bool
    {
        if ($this->getHash($algo) === $hash) return true;

        throw new \RuntimeException(sprintf('File [%s] does not match expected hash.', $this->getRealPath()));
    }

    public function verifySize($size): bool
    {
        if ($this->getSize() === $size) return true;

        throw new \RuntimeException(sprintf(
            'File [%s] does not match expected size. (Is: %s, Compared: %s)',
            $this->getRealPath(),
            $this->getSize(),
            $size
        ));
    }
}
