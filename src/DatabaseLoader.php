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
        if($this->domain_id != null) {
            $result = $this->replaceNullValues($result, $group, $locale);
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
    public function addTranslation($locale, $group, $key) {
        if(!\Config::get('app.debug') || \Config::get('translation-db.minimal')) return;

        // Extract the real key from the translation.
        if (preg_match("/^{$group}\.(.*?)$/sm", $key, $match)) {
            $name = $match[1];
        } else {
            throw new TranslationException('Could not extract key from translation.');
        }

        $domainIdsWithName = \DB::table('translations')
            ->where('name', $name)
            ->groupBy('domain_id')
            ->lists('domain_id');
        $domainIds = \DB::table('translations')
            ->whereNotIn('domain_id', $domainIdsWithName)
            ->groupBy('domain_id')
            ->lists('domain_id');

        foreach ($domainIds as $domainId) {
            $locales = \DB::table('translations')
                ->where('domain_id', $domainId)
                ->groupBy('locale')
                ->lists('locale');

            foreach ($locales as $listLocale) {
                $data = compact('group', 'name');
                $data['domain_id'] = $domainId;
                $data['locale'] = $listLocale;
                $data['viewed_at'] = date_create();
                $data['updated_at'] = date_create();
                $data['created_at'] = date_create();

                \DB::table('translations')->insert($data);
            }
        }

        if ($this->_app['config']->get('translation-db.update_viewed_at')) {
            foreach ($domainIdsWithName as $domainId) {
                $data = [];
                $data['viewed_at'] = date_create();
                \DB::table('translations')->where('domain_id', $domainId)->where('group', $group)->where('name', $name)->update($data);
            }
        }
    }

    protected function replaceNullValues($results, $group, $locale) {
        $default = $this->_app['config']->get('translation-db.default_translation');
        if(count($results) <= 0) {
            $localization = ($locale == $this->_app['config']->get('app.fallback_locale')) ? $default : $locale;
            $results = \DB::table('translations')
                ->where('group', $group)
                ->where('locale', $localization)
                ->lists('value', 'name');

            if($this->domain_id != null && $localization != $default) {
                $results = $this->replaceNullValues($results, $group, $locale);
            }
        }else {
            foreach ($results as $name => $value) {
                if ($value == "" || $value == null) {
                    $query = \DB::table('translations')
                        ->select('value')
                        ->where('group', $group)
                        ->where('name', $name)
                        ->where('locale', $default)
                        ->first();
                    $results[$name] = ($query != null) ? $query->value : '';
                }
            }
        }
        return $results;
    }
}
