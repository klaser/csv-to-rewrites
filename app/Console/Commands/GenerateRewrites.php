<?php

namespace App\Console\Commands;

use App\Exceptions\InfileCannotBeReadOrDoesNotExistException;
use App\Exceptions\OutfileNotWriteable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;

use League\Csv\Reader;
use League\Csv\Statement;

class GenerateRewrites extends Command
{
    const PATH_IGNORE = ['scheme', 'host'];
    const GLUE_PATH = "";
    const GLUE_QUERY = "?";
    const GLUE_FRAGMENT = "#";
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rewrites:csv
                               {--sample : Generate a Sample CSV file}
                               {infile? : The input CSV file}
                               {outfile? : The output text file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert a CSV to a Rewrites file for nginx';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws InfileCannotBeReadOrDoesNotExistException
     * @throws OutfileNotWriteable
     */
    public function handle()
    {
        $infile = $this->argument('infile');
        $outfile = $this->argument('outfile');
        $isSample = $this->option('sample');

        if ($isSample){
            // Output the sample file to the console
            return;
        }

        // Test output first
        if (!$this->outputable($outfile)){
            throw new OutfileNotWriteable();
        }

        if (file_exists($infile)){
            $artifacts = $this->extract($infile);
            $output = $this->transform($artifacts);
            $this->output($outfile, $output);
        } else {
            throw new InfileCannotBeReadOrDoesNotExistException();
        }

    }

    /**
     * Reads $file and converts it to an array
     * @param $file
     * @return mixed
     */
    protected function extract($file){
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0); //set the CSV header offset

        $stmt = new Statement();

        $records = $stmt->process($csv);

        return $records;
    }

    /**
     * Transform the artifacts into an output string
     *
     * @param $artifacts
     * @return string
     */
    protected function transform($artifacts){
        $output = [];

        foreach ($artifacts as $artifact) {
            // Remove the path from the url
            $oldPath = $this->parseUrl($artifact['old']);
            $newPath = $this->parseUrl($artifact['new']);

            // Check for special characters. If they exist, add a line for regular and decoded
            if (stristr($oldPath, '%')){
                // $output[] = sprintf("rewrite ^%s$ %s? permanent;%s", urldecode($oldPath), $newPath, PHP_EOL);
            }

            // Construct new line
            $output[] = sprintf("rewrite ^%s$ %s? permanent;%s", $oldPath, $newPath, PHP_EOL);
        }

        // Combine into a string and return
        return join('', $output);
    }

    /**
     * Write the transformed output to a file
     *
     * @param $path
     * @param $transformedOutput
     */
    protected function output($path, $transformedOutput){
        file_put_contents($path, $transformedOutput);
    }

    /**
     * Returns the writability of $path
     * @param $path
     * @return bool
     * @deprecated latest
     */
    protected function outputable($path){
        return true;

        // return is_writable($path);
    }

    /**
     * Transform a URL to be compatible with the rewrite engine.
     *
     * @param string $withUrl
     * @return void
     */
    protected function parseUrl($withUrl){
        $urlParts = collect(parse_url($withUrl));

        $parsedParts = $urlParts->map(function ($item, $key){
            if (in_array($key, self::PATH_IGNORE)){
                return null;
            }

            return constant("self::GLUE_" . strtoupper($key)) . $item;
        });

        return $parsedParts->join('');
    }
}
