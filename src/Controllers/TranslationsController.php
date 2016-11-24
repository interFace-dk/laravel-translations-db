<?php namespace Hpolthof\Translation\Controllers;

use Hpolthof\Translation\TranslationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Stichoza\GoogleTranslate\TranslateClient;

class TranslationsController extends Controller {
    protected $app, $domain_id;
    public function __construct() {
        // Disable the Laravel Debugbar
        $this->app = app();

        $id = $this->app['config']->get('translation-db.get_domain');
        $this->domain_id = (is_int($id)) ? $id : $id(); //either integer or function...

        if($this->app->offsetExists('debugbar') && $this->app['config']->get('translation-db.disable_debugbar')) {
            $this->app['debugbar']->disable();
        }
    }

    public function getIndex() {
        return view('translation::index');
    }

    public function getGroups() {
        return \DB::table('translations')
            ->select('group')
            ->where('domain_id', $this->domain_id)
            ->distinct()
            ->orderBy('group')
            ->pluck('group');
    }

    public function getLocales() {
        return \DB::table('translations')
            ->select('locale')
            ->where('domain_id', $this->domain_id)
            ->distinct()
            ->orderBy('locale')
            ->pluck('locale');
    }

    public function postItems(Request $request) {
        if(strlen($request->get('translate')) == 0) throw new TranslationException('We need to know what to translate it to');

        $base = \DB::table('translations')
            ->select('name', 'value')
            ->where('locale', $request->get('locale'))
            ->where('group', $request->get('group'))
            ->where('domain_id', $this->domain_id)
            ->orderBy('name')
            ->get();
        $new = \DB::table('translations')
            ->select('name', 'value')
            ->where('locale', strtolower($request->get('translate')))
            ->where('group', $request->get('group'))
            ->where('domain_id', $this->domain_id)
            ->orderBy('name')
            ->pluck('value', 'name');

        foreach($base as &$item) {
            $translate = null;

            if(array_key_exists($item->name, $new)) {
                $translate = $new[$item->name];
            }
            $item->translation = $translate;
        }

        return $base;
    }

    public function postStore(Request $request) {
        $item = \DB::table('translations')
            ->where('locale', strtolower($request->get('locale')))
            ->where('group', $request->get('group'))
            ->where('domain_id', $this->domain_id)
            ->where('name', $request->get('name'))->first();

        $data = [
            'locale' => strtolower($request->get('locale')),
            'group' => $request->get('group'),
            'name' => $request->get('name'),
            'value' => $request->get('value'),
            'domain_id' => $this->domain_id,
            'updated_at' => date_create(),
        ];

        if($item === null) {
            $data = array_merge($data, [
                'created_at' => date_create(),
            ]);
            $result = \DB::table('translations')->insert($data);
        } else {
            $result = \DB::table('translations')->where('id', $item->id)->update($data);
        }

        if(!$result) {
            throw new TranslationException('Database error...');
        }
        return 'OK';
    }

    public function postTranslate(Request $request) {
        $text = TranslateClient::translate($request->input('origin'), $request->input('target'), $request->input('text'));
        $key = $request->input('key');
        return compact('key', 'text');
    }

    public function postDelete(Request $request)
    {
        \DB::table('translations')
            ->where('name', strtolower($request->get('name')))
            ->where('domain_id', $this->domain_id)->delete();
        return 'OK';
    }
}