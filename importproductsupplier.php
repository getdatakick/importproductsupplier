<?php
/**
 * Copyright (C) 2017-2019 Petr Hucik <petr@getdatakick.com>
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@getdatakick.com so we can send you a copy immediately.
 *
 * @author    Petr Hucik <petr@getdatakick.com>
 * @copyright 2023-2023 Petr Hucik
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use ImportProductSupplierModule\ProductSupplierImportEntityType;

class ImportProductSupplier extends Module
{
    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'importproductsupplier';
        $this->tab = 'back_office_features';
        $this->version = '1.0.0';
        $this->author = 'datakick';
        $this->need_instance = 0;
        $this->controllers = [];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Import Product Supplier');
        $this->description = $this->l('CSV Import: ability to import product suppliers');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->tb_versions_compliancy = '>= 1.5.0';
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        return (
            parent::install() &&
            $this->registerHook('actionRegisterImportEntities')
        );
    }

    /**
     * @return ProductSupplierImportEntityType
     */
    public function hookActionRegisterImportEntities()
    {
        require_once(__DIR__ . '/src/ProductSupplierImportEntityType.php');
        return new ProductSupplierImportEntityType();
    }
}
