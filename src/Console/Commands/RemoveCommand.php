<?php namespace Hpolthof\Translation\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class RemoveCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'translation:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes defined translation from the translations table.';

    /**
     * The group to look for in translations table
     *
     * @string
     */
    protected $group;

    /**
     * The name to look for in the translations table
     *
     * @string
     */
    protected $translationName;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire() {
        $this->setArguments();
        $this->removeTranslations();
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments() {
        return [
            ['group', InputArgument::REQUIRED, 'Name of the group to remove from.'],
            ['name', InputArgument::REQUIRED, 'The array input parameters'],
        ];
    }

    protected function setArguments() {
        $this->group = $this->sanitize($this->argument('group'));
        $this->translationName = $this->sanitize($this->argument('name'));
    }

    protected function sanitize($str) {
        return explode('=', $str)[1];
    }

    protected function removeTranslations() {
        $query = \DB::table('translations')
            ->where('group', $this->group)
            ->where('name', $this->translationName);

        $count = $query->count();
        if($count > 0) {
            $query->delete();
            $this->info("{$count} translation(s) were removed from the translations table");
        } else {
            $this->info('No translations were removed');
        }
    }
}
