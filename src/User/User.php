<?php

namespace kissj\User;

use LeanMapper\Entity;

/**
 * Class User
 * @property int $id
 * @property string $email
 */
class User extends Entity {

	public function toString(\DateTime $val): string {
		return $val->format(DATE_ISO8601);
	}

	// TODO rename more verbally (getDateFromString?)
	public function fromString(string $val): string {
		return new \DateTime($val);
	}
	
	/**
	 * @return string - 'patrol-leader' || 'ist' || 'guest'
	 */
	public function getRole(): string {
		// TODO implement
		return 'patrol-leader';
	}
}