<?php namespace Hpolthof\Translation;;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;

class Translator extends \Illuminate\Translation\Translator implements TranslatorContract {

	protected $app = null;

	public function __construct(Loader $database, Loader $loader, $locale, Application $app)
	{
		$this->database = $database;
		$this->app = $app;
		parent::__construct($loader, $locale);
	}

	protected static function isNamespaced($namespace)
	{
		return !(is_null($namespace) || $namespace == '*');
	}

	/**
	 * Get the translation for the given key.
	 *
	 * @param  string  $key
	 * @param  array   $replace
	 * @param  string  $locale
	 * @param  bool	   $fallback
	 * @return string
	 */
	public function get($key, array $replace = array(), $locale = null, $fallback = true)
	{
		list($namespace, $group, $item) = $this->parseKey($key);
        $namespace .= '.' . ServiceProvider::getDomainId($this->app);
		// Here we will get the locale that should be used for the language line. If one
		// was not passed, we will use the default locales which was given to us when
		// the translator was instantiated. Then, we can load the lines and return.
		foreach ($this->localeArray($locale) as $locale)
		{
			$this->load($namespace, $group, $locale);

			$line = $this->getLine(
				$namespace, $group, $locale, $item, $replace
			);

			// If we cannot find the translation group in the database nor as a file
			// an entry in the database will be added to the translations.
			// Keep in mind that a file cannot be used from that point.
			if(!self::isNamespaced($namespace) && is_null($line)) {
				// Database stuff
				$this->database->addTranslation($locale, $group, $key);
			}

			if ( ! is_null($line)) break;
		}

		// If the line doesn't exist, we will return back the key which was requested as
		// that will be quick to spot in the UI if language keys are wrong or missing
		// from the application's language files. Otherwise we can return the line.
		if ( ! isset($line)) return $key;

		return $line;
	}

	public function load($namespace, $group, $locale)
	{
		if ($this->isLoaded($namespace, $group, $locale)) return;
        $lines = $this->loader->load($locale, $group, $namespace);

		if(empty($lines)) {
			// If debug is off then cache the result forever to ensure high performance.
			if(!\Config::get('app.debug') || \Config::get('translation-db.minimal')) {
				$that = $this;
				$lines = \Cache::rememberForever('__translations.'.$locale.'.'.$group, function() use ($that, $locale, $group, $namespace) {
					return $that->loadFromDatabase($namespace, $group, $locale);
				});
			} else {
				$lines = $this->loadFromDatabase($namespace, $group, $locale);
			}
		}
		$this->loaded[$namespace][$group][$locale] = $lines;
	}

	/**
	 * @param $namespace
	 * @param $group
	 * @param $locale
	 * @return array
	 */
	protected function loadFromDatabase($namespace, $group, $locale)
	{
		$lines = $this->database->load($locale, $group, $namespace);

		if (empty($lines) && \Config::get('translation-db.file_fallback', false)) {
			return $this->loader->load($locale, $group, $namespace);
		}

		return $lines;
	}
}
