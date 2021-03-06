<?php declare(strict_types=1);
namespace T3\Dce\UpdateWizards\Traits;

/*  | This extension is made with love for TYPO3 CMS and is licensed
 *  | under GNU General Public License.
 *  |
 *  | (c) 2020 Armin Vieweg <armin@v.ieweg.de>
 */
use T3\Dce\Utility\DatabaseUtility;
use T3\Dce\Utility\File;

trait MigrateOldNamespacesInFluidTemplateTrait
{
    protected $namespaceOld = '{namespace dce=Tx_Dce_ViewHelpers}';
    protected $namespaceOld2 = '{namespace dce=ArminVieweg\Dce\ViewHelpers}';
    protected $namespaceOld3 = '{namespace dce=T3\Dce\ViewHelpers}';
    protected $namespaceNew = '';


    public function update(): ?bool
    {
        $queryBuilder = DatabaseUtility::getConnectionPool()->getQueryBuilderForTable('tx_dce_domain_model_dce');
        $dceRows = $queryBuilder
            ->select('*')
            ->from('tx_dce_domain_model_dce')
            ->execute()
            ->fetchAll();

        foreach ($dceRows as $dceRow) {
            // Frontend Template
            if ($dceRow['template_type'] === 'file') {
                $this->updateFileTemplate($dceRow, 'template_file');
            } else {
                $this->updateInlineTemplate($dceRow, 'template_content');
            }

            // Backend Templates
            if ($dceRow['backend_template_type'] === 'file') {
                $this->updateFileTemplate($dceRow, 'backend_template_file');
            } else {
                $this->updateInlineTemplate($dceRow, 'backend_template_content');
            }

            // Detail Template
            if ($dceRow['detailpage_template_type'] === 'file') {
                $this->updateFileTemplate($dceRow, 'detailpage_template_file');
            } else {
                $this->updateInlineTemplate($dceRow, 'detailpage_template');
            }

            // Container Template
            if ($dceRow['enable_container']) {
                if ($dceRow['container_template_type'] === 'file') {
                    $this->updateFileTemplate($dceRow, 'container_template_file');
                } else {
                    $this->updateInlineTemplate($dceRow, 'container_template');
                }
            }
        }
        return true;
    }

    /**
     * Checks if given inline template requires update
     *
     * @param array $dceRow
     * @param string $column
     * @return bool
     */
    protected function doesInlineTemplateRequiresUpdate(array $dceRow, string $column) : bool
    {
        return $this->templateNeedUpdate($dceRow[$column] ?? '');
    }

    /**
     * Checks if given file template requires update
     *
     * @param array $dceRow
     * @param string $column
     * @return bool
     */
    protected function doesFileTemplateRequiresUpdate(array $dceRow, string $column) : bool
    {
        $file = File::get($dceRow[$column]);
        if (empty($file)) {
            return false;
        }
        return $this->templateNeedUpdate(file_get_contents($file));
    }


    /**
     * Checks if given code needs an update
     *
     * @param string $templateContent
     * @return bool
     */
    protected function templateNeedUpdate(string $templateContent) : bool
    {
        return strpos($templateContent, $this->namespaceOld) !== false ||
            strpos($templateContent, $this->namespaceOld2) !== false ||
            strpos($templateContent, $this->namespaceOld3) !== false ||
            strpos($templateContent, 'dce:format.raw') !== false ||
            strpos($templateContent, 'dce:image') !== false  ||
            strpos($templateContent, 'dce:uri.image') !== false ;
    }


    /**
     * Updates inline templates in given DCE row
     *
     * @param array $dceRow
     * @param string $column
     * @return bool|null Returns true on success, false on error and null if no update has been performed.
     */
    protected function updateInlineTemplate(array $dceRow, string $column) : ?bool
    {
        $templateContent = $dceRow[$column] ?? '';
        if ($this->templateNeedUpdate($templateContent)) {
            $updatedTemplateContent = $this->performTemplateUpdates($templateContent);

            $connection = DatabaseUtility::getConnectionPool()->getConnectionForTable('tx_dce_domain_model_dce');
            return (bool)$connection->update(
                'tx_dce_domain_model_dce',
                [
                    $column => $updatedTemplateContent
                ],
                [
                    'uid' => (int) $dceRow['uid']
                ]
            );
        }
        return null;
    }

    /**
     * Updates file based templates in given DCE row
     *
     * @param array $dceRow
     * @param string $column
     * @return bool|null Returns true on success, false on error and null if no update has been performed.
     */
    protected function updateFileTemplate(array $dceRow, string $column) : ?bool
    {
        $file = File::get($dceRow[$column]);
        if (!is_writeable($file)) {
            return false;
        }

        $templateContent = file_get_contents($file);
        if ($this->templateNeedUpdate($templateContent)) {
            $updatedTemplateContent = $this->performTemplateUpdates($templateContent);
            if (!file_exists($file)) {
                $file = PATH_site . $file;
            }
            return (bool) file_put_contents($file, $updatedTemplateContent);
        }
        return null;
    }

    /**
     * Performs updates to given DCE template code
     *
     * @param string $templateContent
     * @return string
     */
    protected function performTemplateUpdates(string $templateContent) : string
    {
        $content = str_replace(
            [$this->namespaceOld, $this->namespaceOld2, $this->namespaceOld3],
            [$this->namespaceNew, $this->namespaceNew, $this->namespaceNew],
            $templateContent
        );
        $content = str_replace('dce:format.raw', 'f:format.raw', $content);
        $content = str_replace('dce:image', 'f:image', $content);
        $content = str_replace('dce:uri.image', 'f:uri.image', $content);
        return $content;
    }
}
