<?php

ini_set('display_errors', 1);
ini_set('max_execution_time', 900);
// error_reporting(E_ALL ^ (E_NOTICE | E_WARNING));

class Apriori{
    /*===================================================================================================
    ||  DEKLARASI PROPERTI
    ||      - $data                 : Menyimpan data masuk dari request frontend (dataset)
    ||      - $itemsets             : Menyimpan data itemset
    ||      - $assoc_rules          : Menyimpan data asosiasi item
    ||      - $thresholds           : Menyimpan data konstanta min_sup dan min_conf yang ditetapkan user
    ||      - $last_iteration       : Menyimpan data iterasi terakhir
    ||      - $current_iteration    : Menyimpan data iterasi sekarang
    ||===================================================================================================
    */
    private $data;
    private $itemsets = [];
    private $assoc_rules = [];
    private $thresholds = [
        'min_sup' => 5,
        'min_conf' => 5
    ];
    private $last_iteration = 0;
    private $current_iteration = 0;

    /*===================================================================================================
    ||  SETTER AND GETTER
    ||===================================================================================================
    */
    public function set_data($_data){
        $this->data = $_data;
    }

    public function get_data(){
        return $this->data;
    }

    public function get_itemsets(){
        return $this->itemsets;
    }

    public function get_assoc_rules(){
        return $this->assoc_rules;
    }

    /*=====================================================================================================================================================
    ||  FUNCTIONS
    ||      - possible()                   : Cek apakah iterasi selanjutnya masih memungkinkan untuk dilakukan
    ||      - get_iteration()              : Generate iterasi saat ini berdasarkan iterasi terbesar pada array itemsets
    ||      - get_min_sup()                : Mengubah nilai min_sup dari persen ke integer berdasarkan jumlah dataset
    ||      - itemset_exists()             : Cek apakah itemset sudah ada atau belum pada array itemsets
    ||      - match()                      : Cek apakah dua array sama atau tidak meskipun urutan berbeda
    ||      - add_new_itemset()            : Menambahkan itemset baru pada array itemsets
    ||      - init_itemset_candidate()     : Menambahkan kandidat itemset baru pada array itemset
    ||      - update_itemset_frequency()   : Update sup_count pada itemset sesuai jumlah itemset tersebut yang terdapat pada dataset
    ||      - init_itemset_large()         : Membuat large itemset dengan memangkas itemset yang sup_count nya kurang dari min_sup
    ||      - init_association_rule()      : Membuat association rule yang nantinya disimpan pada array assoc_rules
    ||=====================================================================================================================================================
    */
    public function possible(){
        return ($this->last_iteration < $this->current_iteration || $this->last_iteration <= 0 || $this->current_iteration <= 0) ? true : false;
    }

    public function get_iteration(){
        $max = 0;

        if($this->itemsets == []){
            $max = 1;
        }else{
            foreach ($this->itemsets as $itemset) {
                if($itemset['iteration'] >= $max){
                    $max = $itemset['iteration'] + 1;
                }
            }
        }
        return $max;
    }

    public function get_min_sup(){
        return floor(count($this->data['dataset'])*($this->thresholds['min_sup']/100));
    }

    public function itemset_exists($_itemset){
        $response = false;

        if($this->itemsets != []){
            foreach ($this->itemsets as $i => $i_value) {
                if($this->match($_itemset, $this->itemsets[$i]['itemset'])){
                    $response = true;
                }
            }
        }

        return $response;
    }

    public function match($str_a, $str_b){
        $response = false;

        $items_a = !is_array($str_a) ? explode(' ', $str_a) : $str_a;
        $items_b = !is_array($str_b) ? explode(' ', $str_b) : $str_b;

        if($this->itemsets){
            natsort($items_a);
            natsort($items_b);

            if(implode(' ', $items_a) == implode(' ', $items_b)){
                $response = true;
            }
        }

        return $response;
    }

    public function add_new_itemset($iteration, $tag){
        $this->itemsets[] = [
            'iteration' => $iteration,
            'itemset' => $tag,
            'sup_count' => 0
            ];
    }

    public function init_itemset_candidate(){
        $iteration = $this->get_iteration();
        if($iteration == 1){
            foreach ($this->data['dataset'] as $d) {
                foreach ($d['tags'] as $tag) {
                    if(!$this->itemset_exists($tag)){
                        $this->add_new_itemset($iteration, $tag);
                    }
                }
            }
        }else{
            foreach ($this->itemsets as $key_prev => $value_prev) {
                if($this->itemsets[$key_prev]['iteration'] == $iteration - 1){
                    foreach ($this->data['dataset'] as $key_data => $value_data) {
                        foreach ($this->data['dataset'][$key_data]['tags'] as $key_tag => $value_tag) {
                            if(!in_array($this->data['dataset'][$key_data]['tags'][$key_tag], explode(' ', $this->itemsets[$key_prev]['itemset']))){
                                $new_itemset = implode(' ', [$this->itemsets[$key_prev]['itemset'], $this->data['dataset'][$key_data]['tags'][$key_tag]]);
                                if(!$this->itemset_exists($new_itemset)){
                                    $this->add_new_itemset($iteration, $new_itemset);
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->update_itemset_frequency($iteration);
    }

    public function update_itemset_frequency($iteration){
        foreach ($this->data['dataset'] as $d => $d_value) {
            foreach ($this->itemsets as $i => $i_value) {
                if($this->itemsets[$i]['iteration'] == $iteration){
                    $intersect_count = 0;
                    foreach (explode(' ', $this->itemsets[$i]['itemset']) as $single_item) {
                        if(in_array($single_item, $this->data['dataset'][$d]['tags'])){
                            $intersect_count++;
                        }
                    }
                    if($intersect_count == count(explode(' ', $this->itemsets[$i]['itemset']))){
                        foreach ($this->itemsets as $existing_itemset => $value) {
                            if($this->itemsets[$existing_itemset]['itemset'] === $this->itemsets[$i]['itemset']){
                                $this->itemsets[$existing_itemset]['sup_count']++;
                            }
                        }
                    }
                }
            }
        }
    }

    public function init_itemset_large(){
        foreach ($this->itemsets as $key => $value) {
            if($this->itemsets[$key]['sup_count'] < $this->get_min_sup()){
                unset($this->itemsets[$key]);
            }
        }
        $this->itemsets = array_values($this->itemsets);

        $this->last_iteration = $this->current_iteration;
        $this->current_iteration = ($this->get_iteration() - 1);
    }

    public function init_association_rule(){
        $input_item = $this->data['input'];
        foreach ($this->itemsets as $i => $i_value) {
            // echo '> Iterasi: '.$this->itemsets[$i]['iteration'].'<br>';
            // echo '> Itemset: '.$this->itemsets[$i]['itemset'].'<br>';
            if($this->itemsets[$i]['iteration'] >= 2){
                $items = explode(' ', $this->itemsets[$i]['itemset']);
                if(in_array($input_item, $items)){
                    $dataset_count = 0;
                    foreach ($this->data['dataset'] as $d => $d_value) {
                        $item_count = 0;
                        foreach ($items as $item) {
                            if(in_array($item, $this->data['dataset'][$d]['tags'])) $item_count++;
                        }
                        if($item_count == count($items)){
                            $dataset_count++;
                            // echo 'Itemset '.$this->itemsets[$i]['itemset'].' ADA di dataset '.$this->data['dataset'][$d]['id'].'<br>';
                        }
                        // else echo 'Itemset '.$this->itemsets[$i]['itemset'].' TIDAK ADA di dataset '.$this->data['dataset'][$d]['id'].'<br>';
                    }
                    // echo 'Itemset '.$this->itemsets[$i]['itemset'].' terdapat pada '.$dataset_count.' dataset<br>';

                    foreach ($this->itemsets as $j => $j_value) {
                        if($this->itemsets[$j]['iteration'] == 1){
                            if($this->match($this->itemsets[$j]['itemset'], $input_item)){
                                $sup_count = $this->itemsets[$j]['sup_count'];
                                $confidence = floor(($dataset_count/$sup_count)*100);
                                // echo 'Confidence dari item '.$input_item.' pada itemset '.$this->itemsets[$i]['itemset'].' adalah '.$dataset_count.'/'.$sup_count.' = '.$confidence.'%<br>';

                                if($confidence >= $this->thresholds['min_conf']){
                                    $assoc_items = implode(' ', array_diff($items, explode(' ', $input_item)));
                                    $this->assoc_rules[] = [
                                        'item' => $input_item,
                                        'assoc_items' => $assoc_items,
                                        'confidence' => $confidence
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

/*===================================================================================================
||  MAIN
||===================================================================================================
*/

/*===================================================================================================
    Format data yang valid:

1. ARRAY:
        $data = [
            'input' => 'jus',
            'dataset' => [
                0 => [
                    'id' => '100',
                    'tags' => [
                        0 => 'roti',
                        1 => 'keju',
                        2 => 'jus'
                    ]
                ],
                1 => [
                    'id' => '200',
                    'tags' => [
                        0 => 'susu',
                        1 => 'roti',
                        2 => 'yoghurt'
                    ]
                ],
                2 => [
                    'id' => '300',
                    'tags' => [
                        0 => 'roti',
                        1 => 'jus',
                        2 => 'susu'
                    ]
                ],
                ....
            ]
        ];

2.) JSON:
        {"input":"jus","dataset":[{"id":"100","tags":["roti","keju","jus"]},{"id":"200","tags":["susu","roti","yoghurt"]},{"id":"300","tags":["roti","jus","susu"]}, ..... }

*/

/*===================================================================================================*/
// Comment salah satu method yang tidak dipakai!

$str_data = $_POST['dataset'];
// $str_data = $_GET['dataset'];

/*===================================================================================================*/

$data = json_decode($str_data, true);

$apr = new Apriori;
$apr->set_data($data);

while ($apr->possible()) {
    $apr->init_itemset_candidate();
    $apr->init_itemset_large();
}

// echo '<pre>';
// print_r($apr->get_itemsets());

$apr->init_association_rule();
// print_r($apr->get_assoc_rules());
echo json_encode($apr->get_assoc_rules());
