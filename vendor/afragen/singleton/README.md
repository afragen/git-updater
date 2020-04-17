# singleton

This is a singleton static proxy generator that I use in several projects instead of creating true Singletons. It was inspired by [Alain Schlesser’s post on Singletons](https://www.alainschlesser.com/singletons-shared-instances/).

I’ve moved this library into it’s own repository so that I will be better able to include it via composer.

I have written it to work with PSR-4.

`composer require afragen/singleton:dev-master`

When using this Singleton class in your project you will create an array of class instances.

## Usage

```php
@param string               $class_name
@param object               $caller     Originating object.
@param null|array|\stdClass $options

Singleton::get_instance( $class_name, $calling_class, $options );
```

This will usually be called as follows.

`Singleton::get_instance( 'MyClass', $this );`

The class object created will also pass the calling object as `$instance[$class_name]->caller`.

I do my best to automatically determine the namespace of the class. If the class is in a subfolder of `src` it will need to be designated in the call as follows.

If PSR-4 is set for the `src` directory and the class lives in `src/MySubDir/MyClass` the corresponding call would be as follows.

`Singleton::get_instance( 'MySubDir\MyClass', $this );`

I’m still learning how to properly set up using composer so this may be updated along the way.
