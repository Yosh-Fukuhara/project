<?php

class Product {
    private $id;
    private $name;
    private $price;
    private $category;
    private $onSale;
    private $stock;

    public function __construct($id, $name, $price, $category, $stock = 10) {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->category = $category;
        $this->onSale = false;
        $this->stock = $stock;
    }

    public function __set($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
    }

    public function __get($property) {
        if (property_exists($this, $property)) {
            return $this->$property;
        }
        return null;
    }

    public function __clone() {
        $this->id = $this->id + 1000;
    }

    public function putOnSale($discountPercentage) {
        if (is_numeric($discountPercentage) && $discountPercentage > 0 && $discountPercentage <= 100) {
            $this->price = $this->price * (1 - ($discountPercentage / 100));
            $this->onSale = true;
            return true;
        }
        return false;
    }

    public function putonsales($discountPercentage) {
        return $this->putOnSale($discountPercentage);
    }

    public function takeOffSale($originalPrice) {
        $this->price = $originalPrice;
        $this->onSale = false;
    }

    public function restock($amount) {
        if (is_int($amount) && $amount > 0) {
            $this->stock += $amount;
            return true;
        }
        return false;
    }
}

class SaleProduct extends Product {
    public $discountPercent;

    public function __construct($id, $name, $price, $category, $discountPercent, $stock = 10) {
        parent::__construct($id, $name, $price, $category, $stock);
        $this->discountPercent = $discountPercent;
        $this->putOnSale($discountPercent);
    }
}
