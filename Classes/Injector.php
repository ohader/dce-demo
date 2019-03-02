<?php /** @noinspection ALL */
namespace T3\Dce;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2012-2019 Armin Vieweg <armin@v.ieweg.de>
 */
use T3\Dce\Components\FlexformToTcaMapper\Mapper;
use T3\Dce\Utility\DatabaseUtility;
use T3\Dce\Utility\File;
use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * DCE Injector
 * Injects code (configuration) for configured DCEs dynamically
 */
class Injector
{
    /**
     * Injects TCA
     * Call this in Configuration/TCA/Overrides/tt_content.php
     *
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    public function injectTca() : void
    {
        $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'][] = [
            0 => 'LLL:EXT:dce/Resources/Private/Language/locallang_db.xml:tx_dce_domain_model_dce_long',
            1 => '--div--'
        ];

        $fieldRowsWithNewColumns = Mapper::getDceFieldRowsWithNewTcaColumns();
        if (\count($fieldRowsWithNewColumns) > 0) {
            $newColumns = [];
            foreach ($fieldRowsWithNewColumns as $fieldRow) {
                $newColumns[$fieldRow['new_tca_field_name']] = ['label' => '', 'config' => ['type' => 'passthrough']];
            }
            ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumns);
        }

        $GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'][] = [
            0 => 'LLL:EXT:dce/Resources/Private/Language/locallang_db.xml:tx_dce_domain_model_dce.miscellaneous',
            1 => '--div--'
        ];

        foreach ($this->getDatabaseDces() as $dce) {
            if ($dce['hidden']) {
                continue;
            }
            $dceIdentifier = $dce['identifier'];

            ExtensionManagementUtility::addTcaSelectItem(
                'tt_content',
                'CType',
                [
                    addcslashes($dce['title'], "'"),
                    $dceIdentifier,
                    $dce['hasCustomWizardIcon']
                        ? 'ext-dce-' . $dceIdentifier . '-customwizardicon'
                        : $dce['wizard_icon'],
                ]
            );

            $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$dceIdentifier] =
                $dce['hasCustomWizardIcon']
                    ? 'ext-dce-' . $dceIdentifier . '-customwizardicon'
                    : $dce['wizard_icon'];

            $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$dceIdentifier] =
                'pi_flexform';
            $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][',' . $dceIdentifier] =
                $this->renderFlexformXml($dce);

            $showAccessTabCode = $dce['show_access_tab']
                ? '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                  --palette--;;hidden,
                  --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.access;access,'
                : '';
            $showMediaTabCode = $dce['show_media_tab']
                ? '--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.media,assets,'
                : '';
            $showCategoryTabCode = $dce['show_category_tab']
                ? '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,categories,'
                : '';


            $paletteIdentifier = 'dce_palette_' . $dceIdentifier;
            $showItem = <<<TEXT
--palette--;;${paletteIdentifier}_head,
--palette--;;$paletteIdentifier,
pi_flexform,$showAccessTabCode$showMediaTabCode$showCategoryTabCode
--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xml:tabs.extended
TEXT;
            $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier . '_head']['canNotCollapse'] = true;
            $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier . '_head']['showitem'] =
                'CType' . ($dce['enable_container'] ? ',tx_dce_new_container' : '');

            $GLOBALS['TCA']['tt_content']['types'][$dceIdentifier]['showitem'] = $showItem;

            if ($dce['palette_fields']) {
                $paletteFields = $dce['palette_fields'];
                // remove access-fields from dce_palette, if Access Tab should be shown
                if (!empty($showAccessTabCode)) {
                    $fieldsToRemove = ['hidden', 'starttime', 'endtime', 'fe_group'];
                    $paletteFields = GeneralUtility::trimExplode(',', $paletteFields, true);
                    $paletteFields = implode(',', array_diff($paletteFields, $fieldsToRemove));
                }
                $paletteFields = str_replace(
                    ['--linebreak1--', '--linebreak2--', '--linebreak3--'],
                    '--linebreak--',
                    $paletteFields
                );
                $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier]['canNotCollapse'] = true;
                $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier]['showitem'] = $paletteFields;

                if (ExtensionManagementUtility::isLoaded('gridelements')) {
                    $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier]['showitem'] .=
                        ',tx_gridelements_container,tx_gridelements_columns';
                }
                if (ExtensionManagementUtility::isLoaded('flux')) {
                    $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier]['showitem'] .=
                        ',tx_flux_column,tx_flux_parent';
                }
            }
        }
    }

    /**
     * Injects plugin configuration
     * Call this in ext_localconf.php
     *
     * @return void
     * @throws \Doctrine\DBAL\DBALException
     */
    public function injectPluginConfiguration() : void
    {
        ExtensionManagementUtility::addPageTSConfig(
            'mod.wizards.newContentElement.wizardItems.dce.header = ' .
            'LLL:EXT:dce/Resources/Private/Language/locallang_db.xml:tx_dce_domain_model_dce_long'
        );

        foreach ($this->getDatabaseDces() as $dce) {
            if ($dce['hidden']) {
                continue;
            }
            $dceIdentifier = $dce['identifier'];

            ExtensionUtility::configurePlugin(
                'T3.dce',
                substr($dceIdentifier, 4),
                [
                    'Dce' => 'show',
                ],
                $dce['cache_dce'] ? [] : ['Dce' => 'show'],
                ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
            );

            if ($dce['direct_output']) {
                $dceIdentifier = $dceIdentifier;
                ExtensionManagementUtility::addTypoScript(
                    'dce',
                    'setup',
                    <<<TYPOSCRIPT
temp.dceContentElement < tt_content.$dceIdentifier.20
tt_content.$dceIdentifier >
tt_content.$dceIdentifier < temp.dceContentElement
temp.dceContentElement >
TYPOSCRIPT
                    ,
                    43
                );
            }

            ExtensionManagementUtility::addTypoScript(
                'dce',
                'setup',
                "# Hide lib.stdheader for DCE with identifier $dceIdentifier
                 tt_content.$dceIdentifier.10 >",
                43
            );

            if ($dce['hide_default_ce_wrap'] &&
                ExtensionManagementUtility::isLoaded('css_styled_content')
            ) {
                ExtensionManagementUtility::addTypoScript(
                    'dce',
                    'setup',
                    "# Hide default wrapping for content elements for DCE with identifier $dceIdentifier}
                     tt_content.stdWrap.innerWrap.cObject.default.stdWrap.if.value := addToList($dceIdentifier)",
                    43
                );
            }

            if ($dce['enable_container'] && ExtensionManagementUtility::isLoaded('fluid_styled_content')) {
                ExtensionManagementUtility::addTypoScript(
                    'dce',
                    'setup',
                    "# Change fluid_styled_content template name for DCE with identifier $dceIdentifier
                     tt_content.$dceIdentifier.templateName = DceContainerElement",
                    43
                );
            }

            if ($dce['wizard_enable']) {
                if ($dce['hasCustomWizardIcon'] && !empty($dce['wizard_custom_icon'])) {
                    $iconRegistry = GeneralUtility::makeInstance(
                        IconRegistry::class
                    );
                    $iconRegistry->registerIcon(
                        "ext-dce-$dceIdentifier-customwizardicon",
                        BitmapIconProvider::class,
                        ['source' => $dce['wizard_custom_icon']]
                    );
                }

                $iconIdentifierCode = $dce['hasCustomWizardIcon']
                    ? "ext-dce-$dceIdentifier-customwizardicon"
                    : $dce['wizard_icon'];

                $wizardCategory = $dce['wizard_category'];
                $flexformLabel = $dce['flexform_label'];
                $title = addcslashes($dce['title'], "'");
                $description = addcslashes($dce['wizard_description'], "'");

                ExtensionManagementUtility::addPageTSConfig(
                    "
                    mod.wizards.newContentElement.wizardItems.$wizardCategory.elements.$dceIdentifier {
                        iconIdentifier = $iconIdentifierCode
                        title = $title
                        description = $description
                        tt_content_defValues {
                            CType = $dceIdentifier
                        }
                    }
                    mod.wizards.newContentElement.wizardItems.$wizardCategory.show := addToList($dceIdentifier)
                    TCEFORM.tt_content.pi_flexform.types.$dceIdentifier.label = $flexformLabel
                    "
                );
            }
        }
    }

    /**
     * Renders Flexform XML for given DCE
     * using DOMDocument
     *
     * @param array $singleDceArray
     * @return string
     */
    public function renderFlexformXml(array $singleDceArray) : string
    {
        $xml = new \DOMDocument();
        $root = $xml->createElement('T3DataStructure');
        $xml->appendChild($root);

        $meta = $xml->createElement('meta');
        $meta->appendChild($xml->createElement('langDisable', 1));
        $meta->appendChild($xml->createElement('langDatabaseOverlay', 1));
        $root->appendChild($meta);

        $sheets = $xml->createElement('sheets');
        foreach ($singleDceArray['tabs'] as $dceTab) {
            $tabRoot = $xml->createElement('ROOT');
            $tab = $xml->createElement('sheet.' . $dceTab['variable']);
            $tab->appendChild($tabRoot);

            $sheetTitle = $xml->createElement('sheetTitle');
            $sheetTitle->appendChild($xml->createCDATASection($dceTab['title']));
            $tabRoot->appendChild($sheetTitle);
            $tabRoot->appendChild($xml->createElement('type', 'array'));

            $tabElements = $xml->createElement('el');
            foreach ($dceTab['fields'] as $dceField) {
                $field = $xml->createElement('settings.' . $dceField['variable']);
                if ($dceField['type'] === "2") {
                    // Section Field
                    $field->appendChild($title = $xml->createElement('title'));
                    $title->appendChild($xml->createCDATASection($dceField['title']));

                    $field->appendChild($tv = $xml->createElement('tx_templatevoila'));
                    $tv->appendChild($title = $xml->createElement('title'));
                    $title->appendChild($xml->createCDATASection($dceField['title']));

                    $field->appendChild($xml->createElement('section', 1));
                    $field->appendChild($xml->createElement('type', 'array'));

                    $section = $xml->createElement('el');
                    $field->appendChild($section);

                    $sectionContainer = $xml->createElement('container_' . $dceField['variable']);
                    $section->appendChild($sectionContainer);

                    $sectionContainer->appendChild($xml->createElement('type', 'array'));
                    $sectionContainer->appendChild($title = $xml->createElement('title'));
                    $title->appendChild($xml->createCDATASection($dceField['section_fields_tag']));

                    $sectionContainer->appendChild($tv = $xml->createElement('tx_templatevoila'));
                    $tv->appendChild($title = $xml->createElement('title'));
                    $title->appendChild($xml->createCDATASection($dceField['title']));

                    $sectionFields = $xml->createElement('el');
                    foreach ($dceField['section_fields'] as $dceSectionField) {
                        $sectionField = $xml->createElement($dceSectionField['variable']);
                        $sectionFields->appendChild($sectionField);

                        $sectionField->appendChild($tce = $xml->createElement('TCEforms'));
                        $tce->appendChild($label = $xml->createElement('label'));
                        $label->appendChild($xml->createCDATASection($dceSectionField['title']));

                        $conf = new \DOMDocument();
                        $conf->loadXML('<root>' . $dceSectionField['configuration'] . '</root>');

                        /** @var \DOMElement $childNode */
                        foreach ($conf->childNodes[0]->childNodes as $childNode) {
                            $node = $xml->importNode($childNode, true);
                            $tce->appendChild($node);
                        }
                    }
                    $sectionContainer->appendChild($sectionFields);
                } else {
                    // Regular fields
                    $field->appendChild($tce = $xml->createElement('TCEforms'));
                    $tce->appendChild($title = $xml->createElement('label'));
                    $title->appendChild($xml->createCDATASection($dceField['title']));

                    $conf = new \DOMDocument();
                    $conf->loadXML('<root>' . $dceField['configuration'] . '</root>');

                    /** @var \DOMElement $childNode */
                    foreach ($conf->childNodes[0]->childNodes as $childNode) {
                        $node = $xml->importNode($childNode, true);
                        $tce->appendChild($node);
                    }
                }
                $tabElements->appendChild($field);
                $tabRoot->appendChild($tabElements);
            }

            $sheets->appendChild($tab);
        }
        $root->appendChild($sheets);
        $output = $xml->saveXML();
        return $xml->saveXML();
    }

    /**
     * Returns all available DCE as array with this format
     * (just most important fields listed):
     *
     * DCE
     *    |_ uid
     *    |_ title
     *    |_ tabs <array>
     *    |    |_ title
     *    |    |_ fields <array>
     *    |        |_ uid
     *    |        |_ title
     *    |        |_ variable
     *    |        |_ configuration
     *    |_ ...
     *
     * @return array with DCE -> containing tabs -> containing fields
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getDatabaseDces() : array
    {
        /** @var $databaseConnection \T3\Dce\Utility\DatabaseConnection */
        $databaseConnection = DatabaseUtility::getDatabaseConnection();

        $tables = $databaseConnection->admin_get_tables();
        if (!\in_array('tx_dce_domain_model_dce', $tables, true) ||
            !\in_array('tx_dce_domain_model_dcefield', $tables, true)
        ) {
            return [];
        }

        $dceModelRows = $databaseConnection->exec_SELECTgetRows(
            '*',
            'tx_dce_domain_model_dce',
            'deleted=0 AND pid=0',
            '',
            'sorting asc'
        );
        $dceFieldRows = $databaseConnection->exec_SELECTgetRows(
            'tx_dce_domain_model_dcefield.*',
            'tx_dce_domain_model_dcefield, tx_dce_domain_model_dce',
            'tx_dce_domain_model_dcefield.deleted=0 AND tx_dce_domain_model_dcefield.pid=0 AND ' .
                'tx_dce_domain_model_dce.deleted=0 AND tx_dce_domain_model_dce.pid=0 AND ' .
                'tx_dce_domain_model_dce.uid=tx_dce_domain_model_dcefield.parent_dce',
            '',
            'tx_dce_domain_model_dce.sorting asc, tx_dce_domain_model_dcefield.sorting asc'
        );
        $dceFieldRowsByParentDce = [];
        foreach ($dceFieldRows as $dceFieldRow) {
            if (!isset($dceFieldRowsByParentDce[$dceFieldRow['parent_dce']])) {
                $dceFieldRowsByParentDce[$dceFieldRow['parent_dce']] = [];
            }
            $dceFieldRowsByParentDce[$dceFieldRow['parent_dce']][] = $dceFieldRow;
        }

        $dceFieldRowsSortedByParentFields = $databaseConnection->exec_SELECTgetRows(
            'tx_dce_domain_model_dcefield.*',
            'tx_dce_domain_model_dcefield',
            'tx_dce_domain_model_dcefield.deleted=0 AND tx_dce_domain_model_dcefield.hidden=0 AND parent_field > 0',
            '',
            'tx_dce_domain_model_dcefield.parent_field asc, tx_dce_domain_model_dcefield.sorting asc'
        );
        $dceFieldRowsByParentDceField = [];
        foreach ($dceFieldRowsSortedByParentFields as $dceFieldRow) {
            if (!isset($dceFieldRowsByParentDceField[$dceFieldRow['parent_field']])) {
                $dceFieldRowsByParentDceField[$dceFieldRow['parent_field']] = [];
            }
            $dceFieldRowsByParentDceField[$dceFieldRow['parent_field']][] = $dceFieldRow;
        }

        $dces = [];
        foreach ($dceModelRows as $row) {
            $tabs = [
                0 => [
                'title' => 'LLL:EXT:dce/Resources/Private/Language/locallang.xml:generaltab',
                'variable' => 'tabGeneral',
                'fields' => []
                ]
            ];
            $index = 0;
            if (empty($dceFieldRowsByParentDce[$row['uid']])) {
                // Skip creation of content elements, for DCEs without fields
                continue;
            }
            foreach ((array) $dceFieldRowsByParentDce[$row['uid']] as $row2) {
                if ($row2['type'] === '1') {
                    // Create new Tab
                    $index++;
                    $tabs[$index] = [];
                    $tabs[$index]['title'] = $row2['title'];
                    $tabs[$index]['variable'] = $row2['variable'];
                    $tabs[$index]['fields'] = [];
                    continue;
                }

                if ($row2['type'] === '2') {
                    $sectionFields = [];
                    foreach ((array) $dceFieldRowsByParentDceField[$row2['uid']] as $row3) {
                        if ($row3['type'] === '0') {
                            // add fields of section to fields
                            $sectionFields[] = $row3;
                        }
                    }
                    $row2['section_fields'] = $sectionFields;
                    $tabs[$index]['fields'][] = $row2;
                } else {
                    // usual element
                    $row2['configuration'] = str_replace('{$variable}', $row2['variable'], $row2['configuration']);
                    $tabs[$index]['fields'][] = $row2;
                }
            }
            if (\count($tabs[0]['fields']) === 0) {
                unset($tabs[0]);
            }

            $row['identifier'] = !empty($row['identifier']) ? 'dce_' . $row['identifier'] : 'dce_dceuid' . $row['uid'];
            $row['tabs'] = $tabs;
            $row['hasCustomWizardIcon'] = $row['wizard_icon'] === 'custom';
            $dces[] = $row;
        }

        if (ExtensionManagementUtility::isLoaded('gridelements')) {
            $dces = $this->ensureGridelementsFieldCompatibility($dces);
        }
        return $dces;
    }

    /**
     * Iterates through given DCE rows and add field "colPos" to DCE palettes
     * if not already set.
     *
     * @param array $dces
     * @return array
     */
    protected function ensureGridelementsFieldCompatibility(array $dces) : array
    {
        foreach ($dces as $key => $dceRow) {
            $paletteFields = GeneralUtility::trimExplode(',', $dceRow['palette_fields'], true);
            if (!\in_array('colPos', $paletteFields, true)) {
                $paletteFields[] = 'colPos';
            }
            $dces[$key]['palette_fields'] = implode(', ', $paletteFields);
        }
        return $dces;
    }
}
