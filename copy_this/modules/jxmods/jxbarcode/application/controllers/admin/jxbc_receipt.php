<?php

/*
 *    This file is part of the module jxBarcode for OXID eShop Community Edition.
 *
 *    The module jxBarcode for OXID eShop Community Edition is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    The module jxBarcode for OXID eShop Community Edition is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with OXID eShop Community Edition.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @link      https://github.com/job963/jxBarcode
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @copyright (C) Joachim Barthel 2012-2017
 *
 */
 
class jxbc_receipt extends jxbc_scan
{
    protected $_sThisTemplate = "jxbc_receipt.tpl";
    
    /**
     * Displays the receipt form and their results
     * 
     * @return string
     */
    public function render()
    {
        parent::render();
        
        $myConfig = oxRegistry::get("oxConfig");
        $sPicUrl = $myConfig->getPictureUrl(FALSE) . 'master/product/1';
        $sPic1Url = $myConfig->getPictureUrl(FALSE) . 'generated/product/' . '1/' . str_replace('*','_',$myConfig->getConfigParam( 'sIconsize' )) . '_' . $myConfig->getConfigParam( 'sDefaultImageQuality' );
        $sIconUrl = $myConfig->getPictureUrl(FALSE) . 'generated/product/' . 'icon/' . str_replace('*','_',$myConfig->getConfigParam( 'sIconsize' )) . '_' . $myConfig->getConfigParam( 'sDefaultImageQuality' );

        $sGtin = $this->getConfig()->getRequestParameter( 'oxgtin' );
        $aOxids = $this->getConfig()->getRequestParameter( 'jxbc_oxid' );
        $aAmount = $this->getConfig()->getRequestParameter( 'jxbc_amount' );
        $sSelOxid = $this->getConfig()->getRequestParameter( 'jxbc_productchoice' );
        
        if (isset($aOxids)) {
            $aProducts = $this->getAllArticles( $aOxids );
            $aProducts = $this->addAmount( $aAmount, $aProducts  );
            $aProducts = array_reverse($aProducts); //reverse due to reverse display in tpl
        }
        else
            $aProducts = array();
        
        // ------ new/next product was scanned
        if (!empty($sGtin)) {

            $aProduct = $this->getArticleByGtin($sGtin); 

            if ($aProduct) {
                if (count($aProduct) == 1) {
                    // only one product found by ean
                    $aOneProduct = array();
                    $aOneProduct = $aProduct[0];
                    if ( $this->yetScanned( $aOneProduct['oxid'], $aProducts ) )
                        $aProducts = $this->increaseAmount( $aOneProduct['oxid'], $aProducts );
                    else
                        array_push( $aProducts, $aOneProduct );
                } else {
                    // multiple entries found
                    $sGtin = '';
                }
            }
        }
        
        //--------- multiple products found, on selected by oxid through user
        if (!empty($sSelOxid)) {
            $sGtin = '';
            if ( $this->yetScanned($sSelOxid,$aProducts) ) {
                $aProducts = $this->increaseAmount( $sSelOxid, $aProducts );
            }
            else {
                $aOneProduct = array();
                $aOneProduct = $this->getArticleById($sSelOxid);
                array_push( $aProducts, $aOneProduct );
            }
            $sLastOxid = $sSelOxid;
        }
        else {
            $sLastOxid = $aOneProduct['oxid'];
        }
        
        if ( (count($aOneProduct) == 0) && ($sGtin != "") ) {
            $this->_aViewData["message"] = "ean-not-found";
        }
        
        if ( $this->getConfig()->getRequestParameter('fnc') == 'jxbcSaveReceipt' ) {
            $aProducts = array();
            $this->_aViewData["message"] = "receipt-saved";
        }
        
        $aProducts = array_reverse($aProducts); //reverse to show newest first
        
        $oModule = oxNew('oxModule');
        $oModule->load('jxbarcode');
        $this->_aViewData["sModuleId"] = $oModule->getId();
        $this->_aViewData["sModuleVersion"] = $oModule->getInfo('version');
        
        $this->_aViewData["picurl"] = $sPicUrl;
        $this->_aViewData["pic1url"] = $sPic1Url;
        $this->_aViewData["iconurl"] = $sIconUrl;
        
        $this->_aViewData["sprevgtin"] = $sPrevGtin;
        $this->_aViewData["lastoxid"] = $sLastOxid;
        $this->_aViewData["aProducts"] = $aProducts;
        $this->_aViewData["aProduct"] = $aProduct;

        return $this->_sThisTemplate;
    }
    
    
    /**
     * Saves the articles
     * 
     * @return null
     */
    public function jxbcSaveReceipt()
    {
        $myConfig = oxRegistry::get("oxConfig");
        
        $aOxids = $this->getConfig()->getRequestParameter( 'jxbc_oxid' );
        $aAmount = $this->getConfig()->getRequestParameter( 'jxbc_amount' );
        $aProducts = $this->getAllArticles( $aOxids );
        $aProducts = $this->addAmount( $aAmount, $aProducts );
        
        $oDb = oxDb::getDb();
        $bConfig_UseShopStock = $myConfig->getConfigParam("bJxBarcodeUseShopStock");
        if ($bConfig_UseShopStock) {
            foreach ($aProducts as $Product) {
                $sSql = "UPDATE oxarticles SET oxstock=oxstock+{$Product['oxamount']} WHERE oxid='{$Product['oxid']}' ";
                //echo $sSql.'<br>';
                $ret = $oDb->Execute($sSql);
            }
        }

        $bConfig_UseInventoryStock = $myConfig->getConfigParam("bJxBarcodeUseInventoryStock");
        if ($bConfig_UseInventoryStock) {
            foreach ($aProducts as $Product) {
                $sSql = "INSERT INTO jxinvarticles (jxartid, jxinvstock) VALUES ('{$Product['oxid']}', {$Product['oxamount']}) "
                      . "ON DUPLICATE KEY UPDATE jxinvstock=jxinvstock+{$Product['oxamount']} ";
                //echo $sSql.'<br>';
                $ret = $oDb->Execute($sSql);
            }
        }

        return;
    }
    
    
    /**
     * Updates the stock of the shop
     * 
     * @return null
     */
    public function jxbcUpdateStock()
    {
        $myConfig = oxRegistry::get("oxConfig");
        
        $aOxids = $this->getConfig()->getRequestParameter( 'jxbc_oxid' );
        $aAmount = $this->getConfig()->getRequestParameter( 'jxbc_amount' );
        $aProducts = $this->getAllArticles( $aOxids );
        $aProducts = $this->addAmount( $aAmount, $aProducts );
        
        $oDb = oxDb::getDb();
        $bConfig_UseShopStock = $myConfig->getConfigParam("bJxBarcodeUseShopStock");
        if ($bConfig_UseShopStock) {
            foreach ($aProducts as $Product) {
                $sSql = "UPDATE oxarticles SET oxstock={$Product['oxamount']} WHERE oxid='{$Product['oxid']}' ";
                //echo $sSql.'<br>';
                $ret = $oDb->Execute($sSql);
            }
        }

        $bConfig_UseInventoryStock = $myConfig->getConfigParam("bJxBarcodeUseInventoryStock");
        if ($bConfig_UseInventoryStock) {
            foreach ($aProducts as $Product) {
                $sSql = "INSERT INTO jxinvarticles (jxartid, jxinvstock) VALUES ('{$Product['oxid']}', {$Product['oxamount']}) "
                      . "ON DUPLICATE KEY UPDATE jxinvstock={$Product['oxamount']} ";
                //echo $sSql.'<br>';
                $ret = $oDb->Execute($sSql);
            }
        }

        return;
    }
    
    
    public function getAllArticles( $aOxids )
    {
        $aProducts = array();
        foreach ($aOxids as $sOxid) {
            array_push( $aProducts, $this->getArticleById($sOxid) );
        }
        return $aProducts;
    }
    
    
    public function increaseAmount( $sOxid, $aProducts )
    {
        foreach ($aProducts as $key => $Product) {
            if ($Product['oxid'] == $sOxid)
                $aProducts[$key]['oxamount'] += 1;
        }
        return $aProducts;
    }
    
    
    public function yetScanned( $sOxid, $aProducts )
    {
        foreach ($aProducts as $key => $Product) {
            if ($Product['oxid'] == $sOxid)
                return TRUE;
        }
        return FALSE;
    }
    
    
    public function addAmount( $aAmount, $aProducts )
    {
        foreach ($aProducts as $key => $Product) {
            $aProducts[$key]['oxamount'] = $aAmount[$key];
        }
        return $aProducts;
    }
    
}

?>