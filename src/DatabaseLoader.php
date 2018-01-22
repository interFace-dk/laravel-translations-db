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
            ->where(function ($q) {
                return $q->where('domain_id', null)
                    ->orWhere('domain_id', $this->domain_id);
            })
            ->orderBy('domain_id', 'desc')
            ->pluck('value', 'name')
            ->toArray();

        if(in_array(null, $result, false)) {
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
            ->pluck('domain_id');
        $domainIds = \DB::table('translations')
            ->whereNotIn('domain_id', $domainIdsWithName)
            ->groupBy('domain_id')
            ->pluck('domain_id');

        foreach ($domainIds as $domainId) {
            $locales = \DB::table('translations')
                ->where('domain_id', $domainId)
                ->groupBy('locale')
                ->pluck('locale');

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

    public function namespaces() {}

    /**
     * Replace null values with their default value
     * @param array $results
     * @param string $group
     * @param string $locale
     * @return array
     */
    protected function replaceNullValues(array $results = [], $group, $locale) {
        $filteredResults = array_filter($results, function ($v, $k) {
            return $v == null;
        }, ARRAY_FILTER_USE_BOTH);

        foreach ($filteredResults as $key => $value) {
            $trans = \DB::table('translations')
                ->select('value')
                ->where('group', $group)
                ->where('locale', $locale)
                ->where('domain_id', null)
                ->first();

            if(!empty($trans)) {
                $results[$key] = $trans->value;
            }
        }
        return $results;
    }
}
