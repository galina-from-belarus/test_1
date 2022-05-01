<?php
/**
* Автор: Галина Бенедиктович
*
* Дата реализации: -
*
* Дата изменения: 01.05.2022 15:30
*
* Класс является простой демонстрацией и не использует утилит для работы с базой данных
*/


/**
* Класс Person осуществляет работу с базой данных.
* Для работы класса требуются константы:
* TABLE_PERSON - содержит название используемой таблицы в базе данных;
* DB_PARAMS - ассоциированный массив настроек для создания подключения PDO по образцу:
*	'db' => 'mysql:host',
*	'host' => 'localhost',
*	'name' => 'test',
*	'user' => 'root',
*	'pass' => 'root'
*
* Подключение к базе данных осуществляется автоматически при создании первого экземпляра класса
* и хранится далее в статической переменной.
*
* Конструктор класса принимает параметры в виде ассоциированного массива, где каждый ключ соответствует
* публичной переменной класса. Для поиска существующей записи в таблице достаточно передать верный id.
*
* Конструктор класса проверяет наличие в базе записи с переданным id, и при отсутствии таковой - 
* создает новую запись. Автоматическая генерация id не используется, как не умопянутая в техническом задании.
*
* Перед сохранением данных осуществляется их валидация в соответствии с техническим заданием. Некорректные данные
* вызывают исключение.
*
* При корректном завершении создания экземпляра конструктор возвращает новый экземпляр класса Person.
*
* После успешного создания экземпляра класса Person публичные функции позволяют:
*	format() - возвращает новый	объект StdClass со всеми полями класса Person. Функция может принимать
*		параметры в виде ассоциативного массива с возможными значениями:
*			'age' => true - преобразование даты рождения в возраст (полных лет)
*			'sex' => true - преобразование пола из числовой формы в формат муж/жен
*	delete() - удаляет запись на основании поля id экземпляра класса
*/

class Person {
	public $id;
	public $name;
	public $surname;
	public $birth_date;
	public $sex;
	public $birth_place;
	
	private static $pdo;
	private static $table;
	
	public function __construct($params) {
		self::pdoActivate();

		$person = self::getFromTable($params['id']);
		if ($person) {
			foreach($person as $key => $value) {
				$this->$key = $value;
			}
			return $this;
		}
		else {
			try {
				self::putToTable($params);
				self::__construct($params);
			}
			catch(Exception $ex) {
				echo $ex;
			}
		}
	}
		
	public function delete() {
		$sql = 'DELETE FROM' . ' '. self::$table . ' ' . 'WHERE id=:id';
		$conn = self::$pdo->prepare($sql)->execute(['id' => $this->id]);
	}
		
	public function format($params = null) {
		$formatted = new StdClass();
		$formatted->id = $this->id;
		$formatted->name = $this->name;
		$formatted->surname = $this->surname;
		
		$formatted->birth_date = $params['age'] ? $this->getAge() : $this->birth_date;
		$formatted->sex = $params['sex'] ? $this->getSex() : $this->sex;
		
		$formatted->birth_place = $this->birth_place;
		
		return $formatted;
	}
	
	public function getAge() {
		$date = DateTime::createFromFormat('Y-m-d', $this->birth_date);
		return $date->diff(new DateTime())->y;
	}
	
	public function getSex() {
		$sex = array(0 => 'муж',
					 1 => 'жен');
		return $sex[$this->sex];
	}
	
	private function pdoActivate() {
		if (is_null(self::$table)) {
			self::$table = TABLE_PERSON;
		}
		if (is_null(self::$pdo)) {
			self::$pdo = new PDO(DB_PARAMS['db'] . '=' . DB_PARAMS['host'] . ';' . 'dbname=' . DB_PARAMS['name'],
  								 DB_PARAMS['user'], DB_PARAMS['pass']);
			self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		return;
	}
		
	private static function getFromTable($id) {
		$sql = 'SELECT * FROM' . ' ' . self::$table . ' ' . 'WHERE id=:id';
		$conn = self::$pdo->prepare($sql);
		$conn->execute(['id' => $id]);
		return $conn->fetch(PDO::FETCH_OBJ);
	}
		
	private static function putToTable($params) {
		$data = self::trimData($params);
		$errors = self::validate($data);
		
		if ($errors) {
			$message = 'Пользователя с таким ID не существует либо неверно введены данные: ';
			throw new Exception($message . implode(', ', $errors));
		}
		else {
			$keys = implode(', ', array_keys($data));
			$values = implode(', ', array_map(function($item) {
												return ':' . $item;
											  },
											  array_keys($data)));
			$sql = 'INSERT INTO' . ' ' . self::$table . "($keys) VALUES ($values)";
			$conn = self::$pdo->prepare($sql);
			$conn->execute($data);
			return true;
		}
	}
		
	private static function trimData($data) {
		foreach($data as $key=>$value) {
			$data[$key] = trim($value);
		}
		return $data;
	}
	

	/** Простая функция валидации
	*
	* 	Единственный принимаемый аргумент - массив данных. Ожидаемый
	*   формат данных - ассоциативный массив с ключами, соответствующими полям класса
	* 	и значениями, допустимыми в соответствии с техническим заданием.
	*
	* 	Функция возвращает массив с сообщениями об ошибках. В случае успешной валидации
	*	возвращаемый массив будет пустым.
	*/
	private static function validate($data) {
		$fields = array('id', 'name', 'surname', 'birth_date', 'sex', 'birth_place');
		$errors = array();
		
		if(array_keys($data) == $fields) {
			if (!$data['id']) {
				$errors['id'] = 'Укажите id';
			}
			if (!self::checkAlphabetic($data['name'])) {
				$errors['name'] = 'Имя должно содержать только буквы';
			}
			if (!self::checkAlphabetic($data['surname'])) {
				$errors['surname'] = 'Фамилия должна содержать только буквы';
			}
			if (!self::checkBirthDate($data['birth_date'])) {
				$errors['birth_date'] = 'Такая дата рождения невозможна';
			}
			if (!self::checkSex($data['sex'])) {
				$errors['sex'] = 'Неверно указан пол';
			}
			if (!$data['birth_place']) {
				$errors['birth_place'] = 'Укажите место рождения';
			}
		}
		else {
			$errors = ['Неверный формат данных'];
		}
		return $errors;
	}
		
	private static function checkAlphabetic($string) {
		return strlen($string) < 256 && preg_match('/^[а-яё]+$/iu', $string);
	}
			
	private static function checkBirthDate($date) {
		$date = DateTime::createFromFormat('Y-m-d', $date);
		return $date && $date <= new DateTime();
	}
			
	private static function checkSex($i) {
		return in_array($i, [0, 1]);
	}
}