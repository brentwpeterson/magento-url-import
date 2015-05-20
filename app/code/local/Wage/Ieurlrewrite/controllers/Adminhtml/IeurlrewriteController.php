<?php

class Wage_Ieurlrewrite_Adminhtml_IeurlrewriteController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Import and export Page
     *
     */
    public function importExportAction()
    {
        $this->_title($this->__('Catalog'))
            ->_title($this->__('Url Rewrite Management'));

        $this->_title($this->__('Import/Export Url Rewrite'));

        $this->loadLayout()
            ->_setActiveMenu('catalog/ieurlrewrite')
            ->_addContent($this->getLayout()->createBlock('ieurlrewrite/adminhtml_importexport'))
            ->renderLayout();
    }

    /**
     * import action from import/export tax
     *
     */
    public function importPostAction()
    {
        if ($this->getRequest()->isPost() && !empty($_FILES['import_urlrewrite_file']['tmp_name'])) {
            try {
                $this->_importRates();
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('ieurlrewrite')->__('Invalid file upload attempt'));
            }
        }
        else {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('ieurlrewrite')->__('Invalid file upload attempt'));
        }
        $this->_redirect('*/*/importExport');
    }

    protected function _importRates()
    {
        $fileName   = $_FILES['import_urlrewrite_file']['tmp_name'];
        $csvObject  = new Varien_File_Csv();
        $csvData = $csvObject->getData($fileName);

        /** checks columns */
        $csvFields  = array(
            0   => Mage::helper('ieurlrewrite')->__('store_id'),
            1   => Mage::helper('ieurlrewrite')->__('id_path'),
            2   => Mage::helper('ieurlrewrite')->__('request_path'),
            3   => Mage::helper('ieurlrewrite')->__('target_path'),
            4   => Mage::helper('ieurlrewrite')->__('entity_type'),
            5   => Mage::helper('ieurlrewrite')->__('product_id'),
            6   => Mage::helper('ieurlrewrite')->__('product_sku'),
            7   => Mage::helper('ieurlrewrite')->__('category_id')
        );

        $csvHeader = array_flip($csvData[0]);
        $unset = array();
        $error = false;

        if ($csvData[0] == $csvFields) {
            foreach ($csvData as $k => $v) {
                if ($k == 0) {
                    continue;
                }

                //end of file has more then one empty lines
                if (count($v) <= 1 && !strlen($v[0])) {
                    continue;
                }
                if ($unset) {
                    foreach ($unset as $u) {
                        unset($v[$u]);
                    }
                }

                if (!$storeId = $v[$csvHeader['store_id']]) {
                    $storeId = 1;
                }

                $idPath = $v[$csvHeader['id_path']];
                $requestPath = $v[$csvHeader['request_path']];
                $targetPath = $v[$csvHeader['target_path']];

                $session = Mage::getSingleton('adminhtml/session');
                try {
                    if ($v[$csvHeader['entity_type']] == 'product') {
                        $product = null;
                        // Validate request path
                        Mage::helper('core/url_rewrite')->validateRequestPath($v[$csvHeader['request_path']]);

                        if ($v[$csvHeader['product_id']]) {
                            $product = Mage::getModel('catalog/product')->load($v[$csvHeader['product_id']]);
                        } elseif ($v[$csvHeader['product_sku']]) {
                            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $v[$csvHeader['product_sku']]);
                        }

                        if ($product) {
                            if (!$idPath) {
                                $idPath = $requestPath;
                            }
                            $targetPath = $product->getUrlPath();
                        }
                    } elseif($v[$csvHeader['entity_type']] == 'category') {
                        $category = null;
                        // Validate request path
                        Mage::helper('core/url_rewrite')->validateRequestPath($v[$csvHeader['request_path']]);

                        if ($v[$csvHeader['category_id']]) {
                            $category = Mage::getModel('catalog/category')->load($v[$csvHeader['category_id']]);
                        }

                        if ($category) {
                            if (!$idPath) {
                                $idPath = $requestPath;
                            }
                            $targetPath = $category->getUrlPath();
                        }
                    }


                    if ($idPath && $requestPath && $targetPath) {
                        Mage::getModel('core/url_rewrite')
                            ->setIsSystem(0)
                            ->setStoreId($storeId)
                            ->setOptions('RP')
                            ->setIdPath($idPath)
                            ->setRequestPath($requestPath)
                            ->setTargetPath($targetPath)
                            ->save();
                    } else {
                        $error = true;
                        Mage::log($requestPath." is not imported", null, 'custom_url_rewrite.log');
                    }
                } catch (Exception $e) {
                    $error = true;
                    Mage::log($requestPath." is not imported", null, 'custom_url_rewrite.log');
                }
            }
            if (!$error) {
                $session->addSuccess(Mage::helper('ieurlrewrite')->__('The Url Rewrite has been imported.'));
            } else {
                $session->addError(Mage::helper('ieurlrewrite')->__('Few Url Rewrite has not been imported. Please check custom_url_rewrite.log'));
            }
        } else {
            Mage::throwException(Mage::helper('ieurlrewrite')->__('Invalid file format upload attempt'));
        }
    }

    /**
     * export action from import/export tax
     *
     */
    public function exportPostAction()
    {
        /** start csv content and set template */
        $headers = new Varien_Object(array(
            'store_id'         => Mage::helper('ieurlrewrite')->__('store_id'),
            'id_path' => Mage::helper('ieurlrewrite')->__('id_path'),
            'request_path'  => Mage::helper('ieurlrewrite')->__('request_path'),
            'target_path' => Mage::helper('ieurlrewrite')->__('target_path')
        ));
        $template = '"{{store_id}}","{{id_path}}","{{request_path}}","{{target_path}}"';
        $content = $headers->toString($template) . "\n";;

        $collection = Mage::getResourceModel('core/url_rewrite_collection')
            ->addFieldToFilter('is_system', 0)
        ;

        while ($urlRewrite = $collection->fetchItem()) {
            $content .= $urlRewrite->toString($template) . "\n";
        }

        $this->_prepareDownloadResponse('custom_url_rewrite.csv', $content);
    }
}
