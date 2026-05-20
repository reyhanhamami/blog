@php
    $orders = [
        [
            'id' => 1,
            'user' => ['image' => './images/user/user-17.jpg', 'name' => 'Lindsey Curtis', 'role' => 'Web Designer'],
            'projectName' => 'Agency Website',
            'team' => ['./images/user/user-22.jpg', './images/user/user-23.jpg', './images/user/user-24.jpg'],
            'budget' => '3.9K',
            'status' => 'Active',
        ],
        [
            'id' => 2,
            'user' => ['image' => './images/user/user-18.jpg', 'name' => 'Kaiya George', 'role' => 'Project Manager'],
            'projectName' => 'Technology',
            'team' => ['./images/user/user-25.jpg', './images/user/user-26.jpg'],
            'budget' => '24.9K',
            'status' => 'Pending',
        ],
        [
            'id' => 3,
            'user' => ['image' => './images/user/user-19.jpg', 'name' => 'Zain Geidt', 'role' => 'Content Writer'],
            'projectName' => 'Blog Writing',
            'team' => ['./images/user/user-27.jpg'],
            'budget' => '12.7K',
            'status' => 'Active',
        ],
        [
            'id' => 4,
            'user' => ['image' => './images/user/user-20.jpg', 'name' => 'Abram Schleifer', 'role' => 'Digital Marketer'],
            'projectName' => 'Social Media',
            'team' => ['./images/user/user-28.jpg', './images/user/user-29.jpg', './images/user/user-30.jpg'],
            'budget' => '2.8K',
            'status' => 'Cancel',
        ],
        [
            'id' => 5,
            'user' => ['image' => './images/user/user-21.jpg', 'name' => 'Carla George', 'role' => 'Front-end Developer'],
            'projectName' => 'Website',
            'team' => ['./images/user/user-31.jpg', './images/user/user-32.jpg', './images/user/user-33.jpg'],
            'budget' => '4.5K',
            'status' => 'Active',
        ],
    ];

    $statusClass = fn ($status) => [
        'Active' => 'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-500',
        'Pending' => 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-400',
        'Cancel' => 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-500',
    ][$status] ?? '';
@endphp

<div>
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3">
        <div class="max-w-full overflow-x-auto custom-scrollbar">
            <table class="w-full min-w-275.5">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">User</p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Project Name</p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Team</p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Status</p>
                        </th>
                        <th class="px-5 py-3 text-left sm:px-6">
                            <p class="font-medium text-gray-500 text-theme-xs dark:text-gray-400">Budget</p>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-5 py-4 sm:px-6" colspan="1">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 overflow-hidden rounded-full">
                                        <img src="{{ $order['user']['image'] }}" alt="{{ $order['user']['name'] }}">
                                    </div>
                                    <div>
                                        <span class="block font-medium text-gray-800 text-theme-sm dark:text-white/90">{{ $order['user']['name'] }}</span>
                                        <span class="block text-gray-500 text-theme-xs dark:text-gray-400">{{ $order['user']['role'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order['projectName'] }}</p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex -space-x-2">
                                    @foreach ($order['team'] as $teamImage)
                                        <div class="w-6 h-6 overflow-hidden border-2 border-white rounded-full dark:border-gray-900">
                                            <img src="{{ $teamImage }}" alt="team member">
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="text-theme-xs inline-block rounded-full px-2 py-0.5 font-medium {{ $statusClass($order['status']) }}">
                                    {{ $order['status'] }}
                                </p>
                            </td>
                            <td class="px-5 py-4 sm:px-6">
                                <p class="text-gray-500 text-theme-sm dark:text-gray-400">{{ $order['budget'] }}</p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
