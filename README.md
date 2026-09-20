# sad_spirit/el2sql: Expression Language to SQL translator

Suppose we need to get all database records satisfying a condition written in [Symfony's ExpressionLanguage].
We can fetch everything from the DB and then filter the result through `$expressionLanguage->evaluate()`,
but a much nicer approach is to translate the expression to SQL and make the database do the job.

This package allows exactly this: given an ExpressionLanguage condition it generates a `WHERE` clause part
in Postgres dialect of SQL.

**TODO: simple example here**

## How does this work?

ExpressionLanguage has a `parse()` method that builds an AST for the expression.
This is then transformed into AST for Postgres dialect of SQL,
which is represented using classes from [pg_builder package].

Transforming ASTs is actually an easier part, the harder part is mapping of object properties to database columns and
translating custom functions. 

## What parts of ExpressionLanguage are supported

**TODO: list stuff that is supported and what it translates to**

## What parts of ExpressionLanguage are *not* supported

### Object methods

These do not have a suitable counterpart in Postgres. You'll have to use (virtual) public properties
instead of getters or plain functions if there is a need to pass additional arguments.

### Null-safe Operator

It does not make sense in the context of this package: if a variable is missing
then a lookup query should be built for that table.

## Example

Let's consider a more complex business rule that may appear in some document approval scenario:
```
document.author in employee.subordinates
```

Two types of queries are possible based on that
- Fetch all documents that are authored by subordinates of the given employee;
- Fetch all managers for the author of the given document.

Additionally, while `document.author` will probably map to a column of the `documents` table,
`employee.subordinates` will probably not be that simple: subordination is often represented using
a department tree with employees linked to the branches of that.

**TODO: add code**


[Symfony's ExpressionLanguage]: https://symfony.com/doc/current/components/expression_language.html
[pg_builder package]: https://github.com/sad-spirit/pg-builder
