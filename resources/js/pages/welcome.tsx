import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="OLX Watcher Bot" />
            <div className="flex min-h-screen flex-col bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <header className="border-b border-[#e3e3e0] px-6 py-4 dark:border-[#3E3E3A]">
                    <div className="mx-auto flex max-w-5xl items-center justify-between">
                        <div className="flex items-center gap-2.5">
                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                className="text-[#f53003] dark:text-[#FF4433]"
                            >
                                <path
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                    stroke="currentColor"
                                    strokeWidth="1.75"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                            <span className="font-semibold tracking-tight">OLX Watcher</span>
                        </div>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-block rounded-sm border border-[#19140035] px-4 py-1.5 text-sm leading-normal hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="px-4 py-1.5 text-sm leading-normal text-[#706f6c] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="inline-block rounded-sm border border-[#19140035] bg-[#1b1b18] px-4 py-1.5 text-sm leading-normal text-white hover:bg-black dark:border-[#3E3E3A] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                                        >
                                            Register
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col px-6 py-16 lg:py-24">
                    {/* Hero */}
                    <div className="mb-16 max-w-2xl">
                        <div className="mb-4 inline-flex items-center gap-2 rounded-full border border-[#e3e3e0] px-3 py-1 text-xs text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]">
                            <span className="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                            Automated OLX monitoring
                        </div>
                        <h1 className="mb-4 text-4xl font-semibold leading-tight tracking-tight lg:text-5xl">
                            Never miss a listing
                            <br />
                            on OLX
                        </h1>
                        <p className="mb-8 text-lg text-[#706f6c] dark:text-[#A1A09A]">
                            Set up watchers for any OLX category, city, and filters.
                            Get instant Telegram notifications the moment a matching
                            listing appears.
                        </p>
                        <div className="flex flex-wrap gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-block rounded-sm bg-[#1b1b18] px-6 py-2.5 text-sm font-medium text-white hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                                >
                                    Go to Dashboard
                                </Link>
                            ) : (
                                <>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="inline-block rounded-sm bg-[#1b1b18] px-6 py-2.5 text-sm font-medium text-white hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                                        >
                                            Get started
                                        </Link>
                                    )}
                                    <Link
                                        href={login()}
                                        className="inline-block rounded-sm border border-[#19140035] px-6 py-2.5 text-sm font-medium hover:border-[#1915014a] dark:border-[#3E3E3A] dark:hover:border-[#62605b]"
                                    >
                                        Sign in
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>

                    {/* Features */}
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {[
                            {
                                icon: (
                                    <path
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        stroke="currentColor"
                                        strokeWidth="1.75"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                ),
                                title: 'Smart watchers',
                                description:
                                    'Configure watchers by category, city, district, and custom filter options to match exactly what you\'re looking for.',
                            },
                            {
                                icon: (
                                    <path
                                        d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                                        stroke="currentColor"
                                        strokeWidth="1.75"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                ),
                                title: 'Telegram notifications',
                                description:
                                    'Receive instant alerts directly in your Telegram chat as soon as a new matching listing is published on OLX.',
                            },
                            {
                                icon: (
                                    <>
                                        <path
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            stroke="currentColor"
                                            strokeWidth="1.75"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </>
                                ),
                                title: 'Continuous syncing',
                                description:
                                    'Listings are synced automatically in the background. The queue worker checks for new items and dispatches notifications without any manual action.',
                            },
                            {
                                icon: (
                                    <>
                                        <path
                                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6-10l6-3m0 13l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 9m0 4V9"
                                            stroke="currentColor"
                                            strokeWidth="1.75"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </>
                                ),
                                title: 'Category & location import',
                                description:
                                    'All OLX categories and locations (cities, districts) are imported and kept up to date so your watchers always reflect the real OLX structure.',
                            },
                            {
                                icon: (
                                    <>
                                        <path
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                            stroke="currentColor"
                                            strokeWidth="1.75"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </>
                                ),
                                title: 'Listing history',
                                description:
                                    'All seen listings are stored so the bot never sends duplicate notifications, even if a listing is temporarily removed and re-posted.',
                            },
                            {
                                icon: (
                                    <>
                                        <path
                                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                            stroke="currentColor"
                                            strokeWidth="1.75"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                        <path
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            stroke="currentColor"
                                            strokeWidth="1.75"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </>
                                ),
                                title: 'Admin panel',
                                description:
                                    'Manage watchers, categories, cities, and filter options through a full-featured Filament admin panel with no coding required.',
                            },
                        ].map(({ icon, title, description }) => (
                            <div
                                key={title}
                                className="rounded-lg border border-[#e3e3e0] bg-white p-5 dark:border-[#3E3E3A] dark:bg-[#161615]"
                            >
                                <div className="mb-3 flex h-8 w-8 items-center justify-center rounded-md border border-[#e3e3e0] dark:border-[#3E3E3A]">
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        xmlns="http://www.w3.org/2000/svg"
                                        className="text-[#706f6c] dark:text-[#A1A09A]"
                                    >
                                        {icon}
                                    </svg>
                                </div>
                                <h3 className="mb-1 text-sm font-medium">{title}</h3>
                                <p className="text-[13px] leading-relaxed text-[#706f6c] dark:text-[#A1A09A]">
                                    {description}
                                </p>
                            </div>
                        ))}
                    </div>
                </main>

                <footer className="border-t border-[#e3e3e0] px-6 py-4 dark:border-[#3E3E3A]">
                    <div className="mx-auto max-w-5xl text-center text-xs text-[#706f6c] dark:text-[#A1A09A]">
                        OLX Watcher Bot &mdash; automated listing monitoring for OLX
                    </div>
                </footer>
            </div>
        </>
    );
}
