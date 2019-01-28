<?php
/**
 * Created by PhpStorm.
 * User: dmytro
 * Date: 1/25/19
 * Time: 13:23
 */

return $static = (object) [
	'token'      => '777937684:AAHqMaF78d20vKQs-n7uVckS3OOW9PZscbI',
	// trans-agent
	'token_test' => '570752214:AAEHXtycph97x8Z6EW8RTPgFBeE2-sLSFdQ',
	// find-restaurant
	'db'         => (object) [
		'host'    => 'mytao.mysql.tools',
		'user'    => 'mytao_telebot',
		'pass'    => 'Ky7!o0d&L4',
		'db_name' => 'mytao_telebot',
	],

	'cmd' => (object) [
		'btn1'  => 'Курс $/¥/₴',
		'btn2'  => 'Задати курс $/¥',
		'btn3'  => 'Задати курс $/₴',
		'btn4'  => 'Калькулятор $/¥/₴',
		'btn5'  => '📈 Побажання',
		'btn6'  => '✉️ Зв\'язатися',
		'start' => '/start',
	],

	'message' => (object) [
		'calculate'  => 'Введіть Вашу суму замовлення в Юанях 👇',
		'wishes'     => 'Напишіть нам свої побажання по функціоналу бота:',
		'wish_saved' => 'Ваше побажання буде розглянуто ❕',
		'info'       => "Написати менеджеру: @DmytroTA\r\n\r\nНаписати розробнику бота: @dmytrov1",
	],
];