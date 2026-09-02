<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class MapTileController extends Controller
{
    /**
     * يمرر بلاطة الخريطة من خادم البلاطات المستضاف ذاتياً.
     *
     * خادم البلاطات يعمل على HTTP فقط، والتطبيق يُقدَّم عبر HTTPS، فلو طلبت
     * Leaflet البلاطة منه مباشرةً لحجبها المتصفح باعتبارها mixed content ولظهرت
     * الخريطة فارغة دون أي خطأ ظاهر. تمريرها من هنا يجعل الطلب من نفس أصل الصفحة.
     */
    public function __invoke(int $z, int $x, int $y): Response
    {
        $limit = 2 ** $z;

        abort_unless($z <= 20 && $x < $limit && $y < $limit, 404);

        $response = Http::timeout((int) config('services.map.timeout'))
            ->get(sprintf(
                '%s/tile/%d/%d/%d.png',
                rtrim((string) config('services.map.tile_server'), '/'),
                $z,
                $x,
                $y
            ));

        abort_unless($response->successful(), 404);

        // البلاطات ثابتة عملياً، فيتكفل المتصفح بتخزينها ولا يتكرر المرور بالتطبيق
        return response($response->body(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=' . (int) config('services.map.cache_ttl'),
        ]);
    }
}
