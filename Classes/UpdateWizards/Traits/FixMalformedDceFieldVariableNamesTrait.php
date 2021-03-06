<?php declare(strict_types=1);
namespace T3\Dce\UpdateWizards\Traits;

/*  | This extension is made with love for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2020 Armin Vieweg <armin@v.ieweg.de>
 */
use T3\Dce\UserFunction\CustomFieldValidation\LowerCamelCaseValidator;
use T3\Dce\UserFunction\CustomFieldValidation\NoLeadingNumberValidator;
use T3\Dce\Utility\DatabaseUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait FixMalformedDceFieldVariableNamesTrait
{

    public function update(): ?bool
    {
        $malformedDceFields = $this->getDceFieldsWithMalformedVariableNames();
        foreach ($malformedDceFields as $malformedDceField) {
            $malformedVariableName = $malformedDceField['variable'];
            // Update DceField
            $connection = DatabaseUtility::getConnectionPool()->getConnectionForTable('tx_dce_domain_model_dcefield');
            $connection->update(
                'tx_dce_domain_model_dcefield',
                [
                    'variable' => $this->fixVariableName($malformedVariableName)
                ],
                [
                    'uid' => (int) $malformedDceField['uid']
                ]
            );

            // Update tt_content records based on the DCE regarding current field
            if ($malformedDceField['parent_dce'] == 0) {
                // get section field and then DCE (thanks god, that section fields are limited to be not nestable!^^)
                $queryBuilder = DatabaseUtility::getConnectionPool()->getQueryBuilderForTable(
                    'tx_dce_domain_model_dcefield'
                );
                $sectionParent = $queryBuilder
                    ->select('*')
                    ->from('tx_dce_domain_model_dcefield')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'uid',
                            $queryBuilder->createNamedParameter($malformedDceField['parent_field'], \PDO::PARAM_INT)
                        )
                    )
                    ->execute()
                    ->fetch();
                $dceUid = $sectionParent['parent_dce'];
            } else {
                $dceUid = $malformedDceField['parent_dce'];
            }

            $queryBuilder = DatabaseUtility::getConnectionPool()->getQueryBuilderForTable('tt_content');
            $contentElements = $queryBuilder
                ->select('*')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->eq(
                        'CType',
                        $queryBuilder->createNamedParameter($this->getDceIdentifier($dceUid))
                    )
                )
                ->execute()
                ->fetchAll();

            foreach ($contentElements as $contentElement) {
                $updatedFlexform = str_replace(
                    [
                        '"settings.' . $malformedVariableName . '"', // Fix variable names
                        '<field index="' . $malformedVariableName . '">' // Fix section field names
                    ],
                    [
                        '"settings.' . $this->fixVariableName($malformedVariableName) . '"',
                        '<field index="' . $this->fixVariableName($malformedVariableName) . '">'
                    ],
                    $contentElement['pi_flexform']
                );

                $connection = DatabaseUtility::getConnectionPool()->getConnectionForTable('tt_content');
                $connection->update(
                    'tt_content',
                    [
                        'pi_flexform' => $updatedFlexform
                    ],
                    [
                        'uid' => (int) $contentElement['uid']
                    ]
                );
            }
        }
        return true;
    }


    /**
     * Returns DceField rows of fields with malformed variable name.
     * A malformed variable:
     * - starts with integer and/or
     * - is not lowerCamelCase
     *
     * @return array DceField rows
     * @see \T3\Dce\UserFunction\CustomFieldValidation\NoLeadingNumberValidator
     * @see \T3\Dce\UserFunction\CustomFieldValidation\LowerCamelCaseValidator
     */
    protected function getDceFieldsWithMalformedVariableNames() : array
    {
        $queryBuilder = DatabaseUtility::getConnectionPool()->getQueryBuilderForTable('tx_dce_domain_model_dcefield');
        $dceFieldRows = $queryBuilder
            ->select('*')
            ->from('tx_dce_domain_model_dcefield')
            ->where('variable != ""')
            ->execute()
            ->fetchAll();

        $lowerCamelCaseValidator = $this->getLowerCamelCaseValidator();
        $noLeadingNumberValidator = $this->getNoLeadingNumberValidator();

        $malformedDceFields = [];
        foreach ($dceFieldRows as $dceFieldRow) {
            $evalLowerCamelCase = $lowerCamelCaseValidator->evaluateFieldValue($dceFieldRow['variable'], true);
            $evalNoLeadingNumber = $noLeadingNumberValidator->evaluateFieldValue($dceFieldRow['variable'], true);
            if ($evalLowerCamelCase !== $dceFieldRow['variable'] || $evalNoLeadingNumber !== $dceFieldRow['variable']) {
                $malformedDceFields[] = $dceFieldRow;
            }
        }
        return $malformedDceFields;
    }

    /**
     * Returns instance of LowerCamelCaseValidator
     *
     * @return LowerCamelCaseValidator
     */
    protected function getLowerCamelCaseValidator() : LowerCamelCaseValidator
    {
        /** @var LowerCamelCaseValidator $lowerCamelCaseValidator */
        $lowerCamelCaseValidator = GeneralUtility::makeInstance(
            LowerCamelCaseValidator::class
        );
        return $lowerCamelCaseValidator;
    }

    /**
     * Returns instance of NoLeadingNumberValidator
     *
     * @return NoLeadingNumberValidator
     */
    protected function getNoLeadingNumberValidator() : NoLeadingNumberValidator
    {
        /** @var NoLeadingNumberValidator $noLeadingNumberValidator */
        $noLeadingNumberValidator = GeneralUtility::makeInstance(
            NoLeadingNumberValidator::class
        );
        return $noLeadingNumberValidator;
    }

    /**
     * Fix given variable name
     *
     * @param string $variableName e.g. "4ExampleValue"
     * @return string "exampleValue"
     */
    protected function fixVariableName(string $variableName) : string
    {
        $lowerCamelCaseValidator = $this->getLowerCamelCaseValidator();
        $noLeadingNumberValidator = $this->getNoLeadingNumberValidator();

        $updatedVariableName = $lowerCamelCaseValidator->evaluateFieldValue($variableName, true);
        $updatedVariableName = $noLeadingNumberValidator->evaluateFieldValue($updatedVariableName, true);
        return $updatedVariableName;
    }

    /**
     * Flattens an array, but makes the delimiter configurable
     *
     * @param array $array
     * @param string $prefix
     * @param string $delimiter
     * @return array
     * @see \TYPO3\CMS\Core\Utility\ArrayUtility::flatten
     */
    protected function flattenArray(array $array, $prefix = '', $delimiter = '.') : array
    {
        $flatArray = [];
        foreach ($array as $key => $value) {
            // Ensure there is no trailing dot:
            $key = rtrim($key, '.');
            if (!\is_array($value)) {
                $flatArray[$prefix . $key] = $value;
            } else {
                $flatArray = array_merge(
                    $flatArray,
                    $this->flattenArray($value, $prefix . $key . $delimiter, $delimiter)
                );
            }
        }
        return $flatArray;
    }
}
