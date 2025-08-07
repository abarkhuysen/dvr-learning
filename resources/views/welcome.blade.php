<x-layouts.welcome :title="__('Welcome')">
    <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
        <main class="flex max-w-[335px] w-full flex-col-reverse lg:max-w-4xl lg:flex-row">
            <div class="flex-1 px-6 py-24 leading-[21px] text-start lg:text-center bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-es-lg rounded-ee-lg lg:rounded-ss-lg lg:rounded-ee-none">
                <h1 class="text-[26px] mb-4 font-bold text-blue-800">{{ config('app.name') }}</h1>
                <p class="text-[14px] mb-2 text-[#706f6c] dark:text-[#A1A09A]">Dorothy van’t Riet Design & Décor Consultants is firm of interior designers, specialising in interior architecture, space planning, décor & styling</p>
                <div class="mt-8 m-auto max-w-full max-lg:min-w-fit lg:max-w-96  flex justify-center">
                    <div>
                        <div class="flex items-end gap-4">
                            @if (Route::has('login'))
                                <nav class="flex items-center justify-center gap-4 pt-4">
                                    @auth
                                        <a
                                            href="{{ url('/dashboard') }}"
                                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                                        >
                                            Dashboard
                                        </a>
                                    @else
                                        <a
                                            href="{{ route('login') }}"
                                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                                        >
                                            Log in
                                        </a>
                                    @endauth
                                </nav>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-[#1a3a5c] dark:bg-[#1a3a5c] relative lg:-ms-px -mb-px lg:mb-0 rounded-t-lg lg:rounded-t-none lg:rounded-e-lg! aspect-[335/376] lg:aspect-auto w-full lg:w-[438px] shrink-0 overflow-hidden">
                <!-- Background video added here -->
                <video autoplay loop muted playsinline class="absolute top-0 left-0 w-full h-full object-cover z-0">
                    <source src="intro.webm" type="video/webm">
                    Your browser does not support the video tag.
                </video>
                <!-- Existing content that should appear on top of the video -->
                <div class="relative z-10 w-full h-full flex items-center justify-center">
                    <!-- You might want to place any content that was previously directly in this div here,
                         or ensure it's styled to be visible over the video.
                         For example, if there was an image, it would go here. -->
                </div>
            </div>
        </main>
    </div>

    @if (Route::has('login'))
        <div class="h-14.5 hidden lg:block"></div>
    @endif
</x-layouts.welcome>
