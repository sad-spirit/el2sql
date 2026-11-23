/*
 * This file is part of sad_spirit/el2sql:
 * Translator of Symfony's ExpressionLanguage to Postgres SQL dialect
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 Data for a two-variable example
 */

begin;

insert into example.departments (id, name) values (1, 'ACME');
insert into example.departments (id, name, parent_id) values (2, 'Engineering', 1);
insert into example.departments (id, name, parent_id) values (3, 'Accounting', 1);
insert into example.departments (id, name, parent_id) values (4, 'Anvils', 2);

alter table example.departments alter id restart 5;

insert into example.employees (id, department_id, first_name, last_name, position, is_manager)
values (1, 1, 'Top', 'Boss', 'CEO', true);
insert into example.employees (id, department_id, first_name, last_name, position, is_manager)
values (2, 1, 'Coffee', 'Bringer', 'Personal Assistant', false);

insert into example.employees (id, department_id, first_name, last_name, position, is_manager)
values (3, 2, 'Engineering', 'Boss', 'Head of Engineering', true);
insert into example.employees (id, department_id, first_name, last_name, position, is_manager)
values (4, 2, 'Paper', 'Worker', 'Assistant', false);

insert into example.employees (id, department_id, first_name, last_name, position, is_manager)
values (5, 3, 'Ka', 'Ching', 'Head of Accounting', true);
insert into example.employees (id, department_id, first_name, last_name, position, is_manager)
values (6, 3, 'Coin', 'Counter', 'Accountant', false);

insert into example.employees (id, department_id, first_name, last_name, position, is_manager)
values (7, 4, 'Dwarven', 'Weaponsmith', 'Anvil Researcher', true);

alter table example.employees alter id restart 8;

insert into example.documents (id, author_id, title, content)
values (1, 7, 'New Anvil Schematics', 'Incomprehensible Dwarven runes');
insert into example.documents (id, author_id, title, content)
values (2, 6, 'Quarterly Report', 'Major losses');

alter table example.documents alter id restart 3;

insert into example.approval_participants (document_id, employee_id, approved) values (1, 6, false);

commit;
