<?php namespace App\Http\Controllers;

use Cache;
use Common\Core\BaseController;
use Common\Database\MigrateAndSeed;
use Common\Settings\DotEnvEditor;
use Common\Settings\Setting;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Schema;

class UpdateController extends BaseController {
    /**
     * @var DotEnvEditor
     */
    private $dotEnvEditor;

    /**
     * @var Setting
     */
    private $setting;

    /**
     * @param DotEnvEditor $dotEnvEditor
     * @param Setting $setting
     */
    public function __construct(DotEnvEditor $dotEnvEditor, Setting $setting)
    {
        $this->dotEnvEditor = $dotEnvEditor;
        $this->setting = $setting;

        if ( ! config('common.site.disable_update_auth') && version_compare(config('common.site.version'), $this->getAppVersion()) === 0) {
            $this->middleware('isAdmin');
        }
    }

    /**
     * Show update view.
     *
     * @return Factory|View
     */
    public function show()
    {
        return view('update');
    }

    /**
     * Perform the update.
     *
     * @return RedirectResponse
     */
    public function update()
	{
        //fix "index is too long" issue on MariaDB and older mysql versions
        Schema::defaultStringLength(191);

        app(MigrateAndSeed::class)->execute();

        $version = $this->getAppVersion();
        $this->dotEnvEditor->write([
            'APP_VERSION' => $version,
            'STATIC_FILE_DELIVERY' => null,
            'UPLOADS_DISK_DRIVER' => 'local',
            'PUBLIC_DISK_DRIVER' => 'local',
        ]);

        Cache::flush();

        return redirect()->back()->with('status', 'Updated the site successfully.');
	}

    /**
     * Get new app version.
     *
     * @return string
     */
    private function getAppVersion()
    {
        try {
            return $this->dotEnvEditor->load(base_path('.env.example'))['app_version'];
        } catch (Exception $e) {
            return '2.2.0';
        }
    }
}
