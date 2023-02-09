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

namespace ImportProductSupplierModule;

use Currency;
use Db;
use DbQuery;
use PrestaShopException;
use Product;
use ProductSupplier;
use Supplier;
use Thirtybees\Core\Import\ImportEntityType;
use Tools;
use Translate;

class ProductSupplierImportEntityType implements ImportEntityType
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->l('Product Suppliers');
    }

    /**
     * @return array|array[]
     */
    public function getAvailableFields(): array
    {
        return [
            'no' => ['label' => $this->l('Ignore this column')],
            'id_supplier' => ['label' => $this->l('Supplier ID')],
            'id_product' => ['label' => $this->l('Product ID')],
            'id_product_attribute'  => ['label' => 'Combination ID'],
            'reference' => ['label' => $this->l('Supplier reference')],
            'price'  => ['label' => $this->l('Supplier price (tax excl.)')],
            'currency' => ['label' => $this->l('Currency')]
        ];
    }

    /**
     * @return bool
     */
    public function supportTruncate(): bool
    {
        return true;
    }

    /**
     * @return bool|string[]
     */
    public function truncate()
    {
        try {
            Db::getInstance()->execute('TRUNCATE ' . _DB_PREFIX_ . 'product_supplier');
            return true;
        } catch (PrestaShopException $e) {
            return [$e->getMessage()];
        }
    }

    /**
     * @param array $data
     * @param bool $validateOnly
     *
     * @return array
     * @throws PrestaShopException
     */
    public function import(array $data, bool $validateOnly)
    {
        $errors = [];
        $warnings = [];

        $productId = 0;
        $supplierId = 0;
        $combinationId = 0;
        $id = 0;
        if ($this->matchByReference($data)) {
            $productSupplier = $this->findProductSupplierByReference($data['reference']);
            if ($productSupplier) {
                $productId = (int)$productSupplier['id_product'];
                $supplierId = (int)$productSupplier['id_supplier'];
                $combinationId = (int)$productSupplier['id_product_attribute'];
                $id = (int)$productSupplier['id_product_supplier'];
            } else {
                $warnings[] = sprintf($this->l('Failed to resolve product by supplier reference "%s"'), $data['reference']);
            }
        } else {
            $productId = $this->resolveProductId($data, $warnings);
            $combinationId = $this->resolveCombinationId($data);
            $supplierId = $this->resolveSupplierId($data, $warnings);
        }

        if ($productId && $supplierId) {
            if (! $id) {
                $id = (int)ProductSupplier::getIdByProductAndSupplier($productId, $combinationId, $supplierId);
            }

            $productSupplier = new ProductSupplier($id);
            $productSupplier->id_product = $productId;
            $productSupplier->id_product_attribute = $combinationId;
            $productSupplier->id_supplier = $supplierId;
            $productSupplier->id_currency = $this->resolveCurrencyId($data);
            if (isset($data['price'])) {
                $productSupplier->product_supplier_price_te = Tools::parseNumber($data['price']);
            }
            if (isset($data['reference'])) {
                $productSupplier->product_supplier_reference = trim($data['reference']);
            }

            $fieldsErrors = $productSupplier->validateFields(false, true);
            if ($fieldsErrors !== true) {
                $errors[] = $fieldsErrors;
            }

            $langErrors = $productSupplier->validateFieldsLang(false, true);
            if ($langErrors !== true) {
                $errors[] = $langErrors;
            }

            if (!$validateOnly && !$errors) {
                $productSupplier->save();
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function l(string $string): string
    {
        return Translate::getModuleTranslation('importproductsupplier', $string, 'importproductsupplier');
    }

    /**
     * @param array $data
     * @param array $warnings
     *
     * @return int
     * @throws PrestaShopException
     */
    private function resolveProductId(array $data, &$warnings)
    {
        $productId = (int)$data['id_product'] ?? 0;
        if ($productId && !Product::existsInDatabase($productId, 'product')) {
            $warnings[] = sprintf($this->l('Product with ID %s not found, ignoring this line'), $productId);
            return 0;
        }
        if (! $productId) {
            $warnings[] = $this->l('Failed to resolve product');
            return 0;
        }
        return $productId;
    }

    /**
     * @param array $data
     * @param array $warnings
     *
     * @return int
     * @throws PrestaShopException
     */
    private function resolveSupplierId(array $data, &$warnings)
    {
        $supplierId = (int)$data['id_supplier'] ?? 0;
        if ($supplierId && !Supplier::existsInDatabase($supplierId, 'supplier')) {
            $warnings[] = sprintf($this->l('Supplier with ID %s not found, ignoring this line'), $supplierId);
            return 0;
        }
        if (! $supplierId) {
            $warnings[] = $this->l('Failed to resolve supplier');
            return 0;
        }
        return $supplierId;
    }

    /**
     * @param array $data
     *
     * @return int
     */
    private function resolveCombinationId(array $data):int
    {
        return (int)$data['id_product_attribute'] ?? 0;
    }

    /**
     * @param array $data
     *
     * @return int
     * @throws PrestaShopException
     */
    private function resolveCurrencyId(array $data):int
    {
        if (isset($data['currency'])) {
            $currency = $data['currency'];
            if (is_int($currency)) {
                return (int)$currency;
            }
            $id = Currency::getIdByIsoCode($currency);
            if ($id) {
                return (int)$id;
            }
        }
        return (int)Currency::getDefaultCurrency()->id;
    }

    /**
     * @param string $reference
     *
     * @return array
     * @throws PrestaShopException
     */
    private function findProductSupplierByReference(string $reference):array
    {
        $conn = Db::getInstance();
        $sql = (new DbQuery())
            ->from('product_supplier')
            ->where('product_supplier_reference = "'.pSQL($reference).'"')
            ;
        $result = $conn->getRow($sql);
        return $result ? $result : [];
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function matchByReference(array $data): bool
    {
        if (isset($data['id_product']) && $data['id_product']) {
            return false;
        }
        return isset($data['reference']) && $data['reference'];
    }

}