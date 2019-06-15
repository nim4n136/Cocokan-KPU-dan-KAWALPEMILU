<?php

/**
 * Cocokan data kpu dan pemilu.
 *
 * @author nim4n <nim4n136@gmail.com>
 * @copyright IAMROOT-LAB
 */
class ScanKPU
{
    private $optionContex = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ];

    private $fileToSave;
    // api KPU untuk nama wilayah
    public $urlNameWilayah = 'https://pemilu2019.kpu.go.id/static/json/wilayah/';
    // api KPU untuk data real count
    public $urlDataKPU = 'https://pemilu2019.kpu.go.id/static/json/hhcw/';
    // api KawalPemilu hasil c1
    public $urlDataKawalPemilu = 'https://kawal-c1.appspot.com/api/c/';
    // url untuk akses website
    public $urlContentKawalPemilu = 'https://kawalpemilu.org/';

    private $dataWilayah = [];

    private function saveDataWilayah($name, $countId = false)
    {
        $count = !$countId ? 0 : $countId;
        $this->dataWilayah[$count] = $name;
    }

    public function startRender($start = false)
    {
        $fetchNamaWilayah = $this->nameWilayah();
        foreach ($this->fetchDataKPU($start) as $key => $value) {

            // skip data luar negro
            if ($key == '-99') {
                continue;
            }
            $this->saveDataWilayah($fetchNamaWilayah->$key->nama, 0);

            // ambil data kpu dan kawal pemilu
            $this->getDataKPUAndKawalPemilu($key);
        }
    }

    public function startByProvinsiID($id)
    {
        // key as id
        $fetchNamaWilayah = $this->nameWilayah();
        $this->saveDataWilayah($fetchNamaWilayah->$id->nama, 0);

        // ambil data kpu dan kawal pemilu
        $this->getDataKPUAndKawalPemilu($id);
    }

    public function getDataKPUAndKawalPemilu($id)
    {
        $extract = explode('/', $id);
        $countParam = count($extract);
        if ($countParam > 4) {
            return;
        }

        $fetchDataKPU = $this->fetchDataKPU($id);

        if ($fetchDataKPU == false) {
            return;
        }

        $fetchNamaWilayah = $this->nameWilayah($id);
        if ($countParam == 4) {
            $fetchKawalPemilu = $this->fetchKawalPemilu(end($extract));
        }

        $number = 0;

        foreach ($fetchDataKPU as $key => $value) {
            $this->saveDataWilayah($fetchNamaWilayah->$key->nama, $countParam);

            if ($countParam == 4) {
                $number++;
                if (isset($fetchKawalPemilu->$number)) {
                    $foundNotSameData = $this->compareData($value, $fetchKawalPemilu->$number->sum, $id, $key);
                    if (!$foundNotSameData) {
                        echo "\n[".implode('/', $this->dataWilayah).'] Data sesuai...';
                    }
                }
            }
            $this->getDataKPUAndKawalPemilu("{$id}/{$key}");
        }

        $number = 0;
    }

    private function compareData($kpu, $kawalPemilu, $id, $key)
    {
        $extractKpu = $this->extractPaslonKpu($kpu);

        if ($extractKpu['jokowi'] == null && $extractKpu['prabowo'] == null) {
            return;
        }

        if (!isset($kawalPemilu->pas2) && !isset($kawalPemilu->pas1)) {
            return;
        }

        if ($kawalPemilu->pas2 == false && $kawalPemilu->pas1 == false) {
            return;
        }

        if ($kawalPemilu->pas1 != $extractKpu['jokowi'] || $kawalPemilu->pas2 != $extractKpu['prabowo']) {
            echo "\n\nDATA TIDAK SAMA DITEMUKAN ..\n";
            echo implode(' > ', $this->dataWilayah);
            $this->showResultTPS($extractKpu, $kawalPemilu);
            $this->saveData($extractKpu, $kawalPemilu, $id, $key);

            return true;
        }
    }

    public function extractPaslonKpu($kpu)
    {
        $jokowiNumber = 21;
        $prabowoNumber = 22;

        return [
            'jokowi'  => $kpu->$jokowiNumber,
            'prabowo' => $kpu->$prabowoNumber,
        ];
    }

    public function saveData($kpu, $kawalPemilu, $id, $key)
    {
        $extract = explode('/', $id);
        if (!file_exists("{$this->dataWilayah[0]}")) {
            mkdir("{$this->dataWilayah[0]}");
        }
        if ($this->fileToSave == false) {
            $this->fileToSave = "{$this->dataWilayah[0]}/hasil-scan-".date('H:i:s').'.txt';
        }
        // collect data
        $data = [
            'waktu_pengambilan_data' 	=> date('d-m-Y H:i:s'),
            'url_tps_kpu' 				        => "{$this->urlDataKPU}{$id}/{$key}.json",
            'url_kawal_pemilu'			     => "{$this->urlContentKawalPemilu}#pilpres:".end($extract),
            'wilayah'					            => implode(' / ', $this->dataWilayah),
            'data_kpu' 			            => [
                'jokowi'  => $kpu['jokowi'],
                'prabowo' => $kpu['prabowo'],
            ],
            'data_kawal_pemilu' => [
                'jokowi'  => $kawalPemilu->pas1,
                'prabowo' => $kawalPemilu->pas2,
            ],
        ];

        $tampilkan = "wilayah 			: {$data['wilayah']}
url_tps_kpu			: {$data['url_tps_kpu']}
url_kawal_pemilu	: {$data['url_kawal_pemilu']}
waktu_pengambilan 	: {$data['waktu_pengambilan_data']}
kpu_jokowi 				: {$data['data_kpu']['jokowi']}
kpu_prabowo 			: {$data['data_kpu']['prabowo']}
kawalpemilu_jokowi		: {$data['data_kawal_pemilu']['jokowi']}
kawalpemilu_prabowo		: {$data['data_kawal_pemilu']['prabowo']}
------\n\n";

        // open file
        $openFile = fopen($this->fileToSave, 'a+');

        // append content
        fwrite($openFile, $tampilkan);

        //close file
        fclose($openFile);
    }

    public function fetchData($url)
    {
        while (true) {
            $fetchData = file_get_contents(
                $url,
                false,
                stream_context_create($this->optionContex)
            );
            if ($fetchData) {
                break;
            }
            echo "\nGagal request! Sedang mencoba kembali \n";
        }

        return $fetchData;
    }

    public function nameWilayah($id = false)
    {
        $paramWilayah = !$id ? '0.json' : "{$id}.json";

        $getNamaWilayah = $this->fetchData($this->urlNameWilayah.$paramWilayah);

        return json_decode($getNamaWilayah);
    }

    public function fetchKawalPemilu($id)
    {
        $getContent = $this->fetchData($this->urlDataKawalPemilu.$id);

        if (isset(json_decode($getContent)->data)) {
            return json_decode($getContent)->data;
        }
    }

    public function fetchDataKPU($id = false)
    {
        $param = !$id ? 'ppwp.json' : "ppwp/{$id}.json";
        $getContent = $this->fetchData($this->urlDataKPU.$param);
        if (isset(json_decode($getContent)->table)) {
            return json_decode($getContent)->table;
        }
    }

    private function parentId($param)
    {
        $extract = explode('/', $param);

        return end($extract);
    }

    public function showResultTPS($kpu, $kawalpemilu)
    {
        echo "\n[KPU]		  [KAWAL PEMILU]";
        echo "\nJokowi  : {$kpu['jokowi']}	| Jokowi   : {$kawalpemilu->pas1}";
        echo "\nPrabowo : {$kpu['prabowo']}	| Prabowo  : {$kawalpemilu->pas2}\n\n";
    }

    public function showProvinci()
    {
        $fetchNamaWilayah = $this->nameWilayah();
        $listId = [];
        foreach ($this->fetchDataKPU() as $key => $value) {
            if ($key == '-99') {
                continue;
            }
            $listId[] = $key;
            echo "$key {$fetchNamaWilayah->$key->nama}\n";
        }

        return $listId;
    }
}
