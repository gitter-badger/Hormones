CREATE TABLE IF NOT EXISTS organs (
	flag TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	name VARCHAR(63) UNIQUE
);
CREATE TABLE IF NOT EXISTS blood (
	id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
	type TINYINT UNSIGNED NOT NULL, -- the hormone type
	receptors INT UNSIGNED DEFAULT 0xFFFFFFFF, -- I suppose 32 bits are enough?
	creation INT NOT NULL,
	tags VARCHAR(1023) DEFAULT ',', -- for convenience of user searching, e.g. INSTR(tags, ',author:shoghicp,')
	json VARCHAR(4095) DEFAULT '{}' -- JSON-formatted miscellaneous data
);
CREATE TABLE tissues (
	id CHAR(36) PRIMARY KEY,
	organ INT NOT NULL,
	laston INT,
	usedslots SMALLINT,
	maxslots SMALLINT
);
