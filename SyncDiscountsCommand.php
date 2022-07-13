<?php
namespace PrestaShop\Module\ErwanDemo\Command;

use PrestaShopCollectionCore;
use PrestaShopLogger;
use SpecificPriceRule;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncDiscountsCommand extends Command
{
    protected static $defaultName = 'erwandemo:discounts';
    protected $errors = false;
    protected $errors_list = [];
    protected $output;

    /**
     * Execute Symfony console command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return 0 or 1
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $ftp_path = _PS_ROOT_DIR_ . '/../custom_path_here/';
        $finder = glob($ftp_path . 'test_file_*.csv');
        $this->processCSV(null);

        if ($finder !== false && !empty($finder)):
            if (count($finder) > 1):
                PrestaShopLogger::addLog("Multiple files will be ignored", 2, null, null, null, true, 1);
            endif;

            //Process file
            $this->processCSV($finder[0]);
        endif;

        if ($this->errors === true):
            foreach ($this->errors_list as $err):
                PrestaShopLogger::addLog("Discounts $err", 4, null, 'Category', null, true, 1);
            endforeach;
            return 1;
        endif;

        return 0;
    }

    /**
     * Read and process CSV file
     *
     * @param [string] $file
     * @return boolean
     */
    private function processCSV($file)
    {
        try {
            if (($csvfile = fopen($file, 'r')) !== false):
                $first = true;
                $currentline = 1;
                while (($item = fgetcsv($csvfile, 0, ";")) !== false):

                    //Ignore csv header line
                    if ($first === true):
                        $first = false;
                        continue;
                    endif;

                    $discounts = [];

                    //Set conditions
                    $conditions = [
                        'category' => $item[1],
                        'subcategory' => $item[2],
                        'product' => $item[3],
                    ];

                    //Scan discount columns
                    for ($i = 4; $i <= 10; $i = $i + 2):
                        if ($item[$i + 1] != '0'):
                            $discounts[] = [
                                'qty' => $item[$i],
                                'amount' => $item[$i + 1],
                            ];
                        endif;
                    endfor;

                    if ($this->addDiscountRulesGroup($discounts, $conditions) === false):
                        $this->errors = true;
                        $this->errors_list = "#$currentline cannot create rule";
                    endif;

                    ++$currentline;

                endwhile;

                //Remove all previous rules
                $this->deleteDiscountRulesGroups();

            else:
                $this->errors = true;
                $this->errors_list = "cannot open $file";
                return false;
            endif;

        } catch (Exception $e) {
            // Catch Prestashop Exception
            $this->errors = true;
            $this->errors_list = "Error: " . $e->getMessage();
            return false;

        }

        return true;
    }

    /**
     * TO DO
     * Add Discount Rule
     *
     * @param [string] $discounts
     * @param [string] $conditions
     * @param [mixed] $line
     * @return boolean
     */
    private function addDiscountRulesGroup($discounts, $conditions, $line)
    {
        foreach ($discounts as $discount):

            if ($conditions['product'] == '0'):
                return $this->createSpecificPriceRule($discounts, $conditions, $line);
            endif;

        endforeach;
    }

    /**
     * Delete previous rules, print to console
     *
     * @return void
     */
    private function deleteDiscountRulesGroups()
    {
        $specificPricesRulesList = new PrestaShopCollectionCore('SpecificPriceRule');

        //Filtering the rules created by the previous import
        foreach ($specificPricesRulesList->where('name', 'like', '% - from my_command_import') as $spr):
            $this->output->writeln('Deleting ' . $spr->name);
            $spr->delete();
        endforeach;

        $this->output->writeln('End');
    }

    /**
     * Create Prestashop SpecificPriceRule
     *
     * @param [string] $discounts
     * @param [string] $conditions
     * @param [mixed] $line
     * @return boolean
     */
    private function createSpecificPriceRule($discounts, $conditions, $line)
    {
        $specificPriceRule = new SpecificPriceRule;
        $specificPriceRule->name = "Price rule $line - from my_command_import";
        $specificPriceRule->id_shop = 1;
        $specificPriceRule->id_currency = 0;
        $specificPriceRule->id_country = 0;
        $specificPriceRule->id_group = 0;
        $specificPriceRule->reduction_tax = 0;
        $specificPriceRule->reduction_type = 'percentage';
        $specificPriceRule->price = -1;

        $specificPriceRule->from_quantity = $discount['qty'];
        $specificPriceRule->reduction = $discount['amount'];

        $conditions_rules = [];

        $categories = new PrestaShopCollectionCore('Category');

        // string Category code
        if ($conditions['category'] != '0'):
            $maincategory = $categories->where('hairdoc_brand_code', '=', $conditions['category'])->getFirst();
            if ($maincategory !== false):
                $conditions_rules[] = [
                    'type' => 'category',
                    'value' => $conditions['category'],
                ];
            endif;
        endif;

        //string Subcategory code
        if ($conditions['subcategory'] != '0'):
            $maincategory = $categories->where('hairdoc_brand_code', '=', $conditions['subcategory'])->getFirst();
            if ($maincategory !== false):
                $conditions_rules[] = [
                    'type' => 'category',
                    'value' => $conditions['subcategory'],
                ];
            endif;
        endif;

        //Save specific price rule
        if ($specificPriceRule->save() === false):
            return false;
        endif;

        //Add conditions if any else return true
        return empty($conditions_rules) ? true : $specificPriceRule->addConditions($conditions_rules);

    }

    /**
     * TO DO
     * Create specific price for a specific Product
     *
     * @param [string] $discounts
     * @param [string] $conditions
     * @param [mixed] $line
     * @return boolean
     */
    private function createProductSpecificPrice($discounts, $conditions, $line)
    {
        // To do
        return true;
    }
}
