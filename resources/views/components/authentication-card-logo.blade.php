@php
/**
 * This file is part of the BFound project.
 *
 * (c) BFound <https://github.com/bfound>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
    $settings = Modules\Core\Entities\Settings::first();
@endphp
<a href="/">
    <img class="size-16" src="{{ asset($settings->getLogoUrl()) }}" alt="">
</a>
