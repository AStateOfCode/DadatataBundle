<?php

namespace Asoc\DadatataBundle\Factory;

class SimpleFilter {

    private $class;
    private $bin;

    public function __construct($class, $bin) {
        $this->class = $class;
        $this->bin = $bin;
    }

    public function get() {
        return new $this->class($this->bin);
    }

} 