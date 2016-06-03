<?php namespace Hpolthof\Translation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Translation\LoaderInterface;

class DatabaseLoader implements LoaderInterface {

    protected $_app = null, $domain_id;

    public function __construct(Application $app, $domain_id)
    {
        $this->_app = $app;
        $this->domain_id = $domain_id;
    }

    /**
     * Load the messages for the given locale.
     *
     * @param  string  $locale
     * @param  string  $group
     * @param  string  $namespace
     * @return array
     */
    public function load($locale, $group, $namespace = null)
    {
        $result = \DB::table('translations')
            ->where('locale', $locale)
            ->where('group', $group)
            ->where('domain_id', $this->domain_id)
            ->lists('value', 'name');
        if($this->domain_id > 0) {
            $result = $this->replaceNullValues($result, $group);
        }
        return $result;
    }

    /**
     * Add a new namespace to the loader.
     * This function will not be used but is required
     * due to the LoaderInterface.
     * We'll just leave it here as is.
     *
     * @param  string  $namespace
     * @param  string  $hint
     * @return void
     */
    public function addNamespace($namespace, $hint) {}

    /**
     * Adds a new translation to the database or
     * updates an existing record if the viewed_at
     * updates are allowed.
     *
     * @param string $locale
     * @param string $group
     * @param string $name
     * @return void
     */
    public function addTranslation($locale, $group, $key)
    {
        $domain_id = $this->domain_id;
        if(!\Config::get('app.debug') || \Config::get('translation-db.minimal')) return;

        // Extract the real key from the translation.
        if (preg_match("/^{$group}\.(.*?)$/sm", $key, $match)) {
            $name = $match[1];
        } else {
            throw new TranslationException('Could not extract key from translation.');
        }

        $item = \DB::table('translations')
            ->where('locale', $locale)
            ->where('group', $group)
            ->where('domain_id', $this->domain_id)
            ->where('name', $name)->first();

        $data = compact('locale', 'group', 'name', 'domain_id');
        $data = array_merge($data, [
            'viewed_at' => date_create(),
            'updated_at' => date_create(),
        ]);

        if($item === null) {
            $data = array_merge($data, [
                'created_at' => date_create(),
            ]);
            \DB::table('translations')->insert($data);
        } else {
            if($this->_app['config']->get('translation-db.update_viewed_at')) {
                \DB::table('translations')->where('id', $item->id)->update($data);
            }
        }
    }

    protected function replaceNullValues($results, $group) {
        foreach ($results as $name => $value) {
            if($value == "" || $value == null) {
                $query = \DB::table('translations')
                    ->select('value')
                    ->where('group', $group)
                    ->where('name', $name)
                    ->where('locale', 'default')
                    ->first();
                $results[$name] = ($query != null) ? $query->value : '';
            }
        }
        return $results;
    }
}
