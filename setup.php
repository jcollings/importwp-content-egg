<?php

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\helpers\CurrencyHelper;
use ImportWP\Common\Addon\AddonBasePanel;
use ImportWP\Common\Addon\AddonInterface;
use ImportWP\Common\Addon\AddonPanelDataApi;
use ImportWP\Common\Model\ImporterModel;

iwp_register_importer_addon('Content Egg', 'iwp-content-egg', function (AddonInterface $addon) {


    // Check to see if content egg is enabled for the current post_type.
    $post_type = (array)$addon->importer_model()->getSetting('post_type');
    $enabled_post_types = GeneralConfig::getInstance()->option('post_types');

    $found = false;
    if (!empty($post_type)) {
        foreach ($enabled_post_types as $enabled) {
            if (in_array($enabled, $post_type)) {
                $found = true;
                break;
            }
        }
    }

    if (!$found) {
        return;
    }

    $addon->register_panel('Content Egg - Offers', 'content_egg', function (AddonBasePanel $panel) {

        $currencies = array_reduce(array_keys(CurrencyHelper::currencies()), function ($carry, $item) {
            $carry[] = ['label' => $item, 'value' => $item];
            return $carry;
        }, []);

        $panel->register_field('Offer Id', 'uniqie_id')
            ->save(false);
        $panel->register_field('Title', 'title', ['core' => true])
            ->save(false);
        $panel->register_field('Product url', 'orig_url', ['core' => true])
            ->save(false);
        $panel->register_field('Rating', 'rating')
            ->save(false);
        $panel->register_field('Domain', 'domain')
            ->save(false);
        $panel->register_field('Custom Deeplink', 'deeplink')
            ->save(false);
        $panel->register_field('Product Image URL', 'img')
            ->save(false);
        $panel->register_field('Merchant Name', 'merchant')
            ->save(false);
        $panel->register_field('Merchant Logo URL', 'logo')
            ->save(false);
        $panel->register_field('Price', 'price')
            ->save(false);
        $panel->register_field('Old Price', 'priceOld')
            ->save(false);
        $panel->register_field('Currency', 'currencyCode')
            ->options($currencies)
            ->save(false);
        $panel->register_field('Custom XPATH Price Selector', 'priceXpath')
            ->save(false);
        $panel->register_field('Description', 'description')
            ->save(false);
        $panel->register_field('Reset offers', 'reset')
            ->options([['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no']])
            ->default('yes')
            ->tooltip('Resetting the feed will purge existing offers.')
            ->save(false);

        $panel->save(function (AddonPanelDataApi $api) {

            $meta = $api->get_meta();

            // required fields
            if (empty($meta) || !isset($meta['title'], $meta['orig_url']) || empty($meta['title']['value']) || empty($meta['orig_url']['value'])) {
                return;
            }

            // unique_id is generated in js using: Math.random().toString(36).slice(2)
            $unique_id = isset($meta['unique_id']['value']) && !empty($meta['unique_id']['value']) ? $meta['unique_id']['value'] : substr(md5($meta['orig_url']['value']), 0, 9);

            $entry = [
                'title' => $meta['title']['value'],
                'orig_url' => $meta['orig_url']['value'],
                'unique_id' => $unique_id,
                'last_update' => time(),
                'stock_status' => '',
                'merchant' => '',
                'img' => '',
                'logo' => '',
                'rating' => null,
                'description' => '',
                'price' => 0.0,
                'extra' => []
            ];

            if (isset($meta['domain']['value'])) {
                $entry['domain'] = $meta['domain']['value'];
            }

            if (isset($meta['price']['value'])) {
                $entry['price'] = $meta['price']['value'];
            }

            if (isset($meta['currencyCode']['value'])) {
                $entry['currencyCode'] = $meta['currencyCode']['value'];
            }

            if (isset($meta['priceOld']['value'])) {
                $entry['priceOld'] = $meta['priceOld']['value'];
            }

            if (isset($meta['description']['value'])) {
                $entry['description'] = $meta['description']['value'];
            }

            if (isset($meta['img']['value'])) {
                $entry['img'] = $meta['img']['value'];
            }

            if (isset($meta['merchant']['value'])) {
                $entry['merchant'] = $meta['merchant']['value'];
            }

            if (isset($meta['logo']['value'])) {
                $entry['logo'] = $meta['logo']['value'];
            }

            if (isset($meta['rating']['value'])) {
                $entry['rating'] = min((int)$meta['rating']['value'], 5);
            }

            if (isset($meta['deeplink']['value'])) {
                $entry['extra']['deeplink'] = $meta['deeplink']['value'];
            }

            if (isset($meta['priceXpath']['value'])) {
                $entry['extra']['priceXpath'] = $meta['priceXpath']['value'];
            }

            if (empty($entry['extra'])) {
                $entry['extra'] = ['deeplink' => ''];
            }

            $data = [];
            $product_session_key = '_iwp_ce_session';

            /**
             * @var ImporterModel $importer_model
             */
            $importer_model = $api->importer_model();
            $importer_session = get_post_meta($importer_model->getId(), '_iwp_session', true);

            if ((isset($meta['reset']['value']) && $meta['reset']['value'] === 'no')  || get_post_meta($api->object_id(), $product_session_key, true) === $importer_session) {
                $data = ContentManager::getData($api->object_id(), 'Offer');
            }

            $data[$unique_id] = $entry;
            ContentManager::saveData($data, 'Offer', $api->object_id());

            update_post_meta($api->object_id(), ContentManager::META_PREFIX_LAST_ITEMS_UPDATE . 'Offer', $entry['last_update']);
            update_post_meta($api->object_id(), $product_session_key, $importer_session);
        });
    });
});
