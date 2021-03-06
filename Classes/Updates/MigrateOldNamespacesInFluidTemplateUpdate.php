<?php
namespace T3\Dce\Updates;

/*  | This extension is made with love for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2012-2020 Armin Vieweg <armin@v.ieweg.de>
 *  |     2019 Stefan Froemken <froemken@gmail.com>
 */
use T3\Dce\UpdateWizards\Traits\MigrateOldNamespacesInFluidTemplateTrait;
use T3\Dce\Utility\DatabaseUtility;

/**
 * Migrates old namespaces in fluid templates
 *
 * @deprecated Not used since TYPO3 10 anymore
 * @see \T3\Dce\UpdateWizards\MigrateOldNamespacesInFluidTemplateUpdateWizard
 */
class MigrateOldNamespacesInFluidTemplateUpdate extends AbstractUpdate
{
    use MigrateOldNamespacesInFluidTemplateTrait;

    /**
     * @var string
     */
    protected $title = 'EXT:dce Migrate old namespaces in fluid templates';

    /**
     * @var string
     */
    protected $identifier = 'dceMigrateOldNamespacesInFluidTemplateUpdate';

    /**
     * Checks whether updates are required.
     *
     * @param string &$description The description for the update
     * @return bool Whether an update is required (TRUE) or not (FALSE)
     */
    public function checkForUpdate(&$description)
    {
        $queryBuilder = DatabaseUtility::getConnectionPool()->getQueryBuilderForTable('tx_dce_domain_model_dce');
        $dceRows = $queryBuilder
            ->select('*')
            ->from('tx_dce_domain_model_dce')
            ->execute()
            ->fetchAll();

        $updateTemplates = 0;
        foreach ($dceRows as $dceRow) {
            // Frontend Template
            if ($dceRow['template_type'] === 'file') {
                $updateTemplates += (int) $this->doesFileTemplateRequiresUpdate($dceRow, 'template_file');
            } else {
                $updateTemplates += (int) $this->doesInlineTemplateRequiresUpdate($dceRow, 'template_content');
            }

            // Backend Templates
            if ($dceRow['backend_template_type'] === 'file') {
                $updateTemplates += (int) $this->doesFileTemplateRequiresUpdate($dceRow, 'backend_template_file');
            } else {
                $updateTemplates += (int) $this->doesInlineTemplateRequiresUpdate($dceRow, 'backend_template_content');
            }

            // Detail Template
            if ($dceRow['detailpage_template_type'] === 'file') {
                $updateTemplates += (int) $this->doesFileTemplateRequiresUpdate($dceRow, 'detailpage_template_file');
            } else {
                $updateTemplates += (int) $this->doesInlineTemplateRequiresUpdate($dceRow, 'detailpage_template');
            }

            if ($dceRow['enable_container']) {
                if ($dceRow['container_template_type'] === 'file') {
                    $updateTemplates += (int) $this->doesFileTemplateRequiresUpdate(
                        $dceRow,
                        'container_template_file'
                    );
                } else {
                    $updateTemplates += (int) $this->doesInlineTemplateRequiresUpdate(
                        $dceRow,
                        'container_template'
                    );
                }
            }
        }

        if ($updateTemplates > 0) {
            $description = 'You have <b>' . $updateTemplates . ' DCE templates</b> with old namespace. ' .
                           'They need to get updated.';
            return true;
        }
        return false;
    }

    /**
     * Performs the accordant updates.
     *
     * @param array &$dbQueries Queries done in this update
     * @param string|array &$customMessages Custom messages
     * @return bool Whether everything went smoothly or not
     */
    public function performUpdate(array &$dbQueries, &$customMessages)
    {
        return (bool) $this->update();
    }
}
