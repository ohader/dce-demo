<?php
namespace T3\Dce\Components\ContentElementGenerator;

/*  | This extension is made for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2012-2019 Armin Vieweg <armin@v.ieweg.de>
 */
use T3\Dce\Components\FlexformToTcaMapper\Mapper;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Class OutputTcaAndFlexForm
 */
class OutputTcaAndFlexForm
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @param InputInterface $input
     */
    public function __construct(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * Injects TCA
     * Call this in Configuration/TCA/Overrides/tt_content.php
     *
     * @return void
     */
    public function generate() : void
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

        foreach ($this->input->getDces() as $dce) {
            $this->generateTcaForDces($dce);
        }
    }

    /**
     * Generates TCA for single DCE
     *
     * @param array $dce DCE row
     * @return void
     */
    protected function generateTcaForDces(array $dce) : void
    {
        if ($dce['hidden'] || $dce['deleted']) {
            return;
        }
        $dceIdentifier = $dce['identifier'];

        ExtensionManagementUtility::addTcaSelectItem(
            'tt_content',
            'CType',
            [
                addcslashes($dce['title'], "'"),
                $dceIdentifier,
                $dce['hasCustomWizardIcon'] ? 'ext-dce-' . $dceIdentifier . '-customwizardicon'
                    : $dce['wizard_icon'],
            ]
        );

        $GLOBALS['TCA']['tt_content']['ctrl']['typeicon_classes'][$dceIdentifier] = $dce['hasCustomWizardIcon']
            ? 'ext-dce-' . $dceIdentifier . '-customwizardicon' : $dce['wizard_icon'];

        $GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$dceIdentifier] = 'pi_flexform';
        $GLOBALS['TCA']['tt_content']['columns']['pi_flexform']['config']['ds'][',' . $dceIdentifier] =
            $this->renderFlexformXml($dce);

        $showAccessTabCode = $dce['show_access_tab']
            ? '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
              --palette--;;hidden,
              --palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.access;access,'
            : '';
        $showMediaTabCode = $dce['show_media_tab']
            ? '--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.media,assets,' : '';
        $showCategoryTabCode = $dce['show_category_tab']
            ? '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:categories,categories,' : '';


        $paletteIdentifier = 'dce_palette_' . $dceIdentifier;
        $showItem = <<<TEXT
--palette--;;${paletteIdentifier}_head,
--palette--;;$paletteIdentifier,
pi_flexform,$showAccessTabCode$showMediaTabCode$showCategoryTabCode
--div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xml:tabs.extended
TEXT;
        $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier . '_head']['canNotCollapse'] = true;
        $GLOBALS['TCA']['tt_content']['palettes'][$paletteIdentifier . '_head']['showitem'] = 'CType' .
            ($dce['enable_container'] ? ',tx_dce_new_container' : '');

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

    /**
     * Renders Flexform XML for given DCE
     * using DOMDocument
     *
     * @param array $singleDceArray
     * @return string
     */
    protected function renderFlexformXml(array $singleDceArray) : string
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
        return $xml->saveXML();
    }
}