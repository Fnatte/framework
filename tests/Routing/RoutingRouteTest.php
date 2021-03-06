<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

class RoutingRouteTest extends PHPUnit_Framework_TestCase {

	public function testBasicDispatchingOfRoutes()
	{
		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->post('foo/bar', function() { return 'post hello'; });
		$this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
		$this->assertEquals('post hello', $router->dispatch(Request::create('foo/bar', 'POST'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/{bar}', function($name) { return $name; });
		$this->assertEquals('taylor', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/{bar}/{baz?}', function($name, $age = 25) { return $name.$age; });
		$this->assertEquals('taylor25', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/{name}/boom/{age?}/{location?}', function($name, $age = 25, $location = 'AR') { return $name.$age.$location; });
		$this->assertEquals('taylor30AR', $router->dispatch(Request::create('foo/taylor/boom/30', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('{bar}/{baz?}', function($name, $age = 25) { return $name.$age; });
		$this->assertEquals('taylor25', $router->dispatch(Request::create('taylor', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('{baz?}', function($age = 25) { return $age; });
		$this->assertEquals('25', $router->dispatch(Request::create('/', 'GET'))->getContent());
		$this->assertEquals('30', $router->dispatch(Request::create('30', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('{foo?}/{baz?}', function($name = 'taylor', $age = 25) { return $name.$age; });
		$this->assertEquals('taylor25', $router->dispatch(Request::create('/', 'GET'))->getContent());
		$this->assertEquals('fred25', $router->dispatch(Request::create('fred', 'GET'))->getContent());
		$this->assertEquals('fred30', $router->dispatch(Request::create('fred/30', 'GET'))->getContent());
	}


	/**
	 * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
	 */
	public function testRoutesDontMatchNonMatchingPathsWithLeadingOptionals()
	{
		$router = $this->getRouter();
		$router->get('{baz?}', function($age = 25) { return $age; });
		$this->assertEquals('25', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());;		
	}


	public function testDispatchingOfControllers()
	{
		$router = $this->getRouter();
		$router->get('foo', 'RouteTestControllerDispatchStub@foo');
		$this->assertEquals('bar', $router->dispatch(Request::create('foo', 'GET'))->getContent());		
	}


	public function testBasicBeforeFilters()
	{
		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->before(function() { return 'foo!'; });
		$this->assertEquals('foo!', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', array('before' => 'foo', function() { return 'hello'; }));
		$router->filter('foo', function() { return 'foo!'; });
		$this->assertEquals('foo!', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', array('before' => 'foo:25', function() { return 'hello'; }));
		$router->filter('foo', function($route, $request, $age) { return $age; });
		$this->assertEquals('25', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', array('before' => 'foo:bar,baz', function() { return 'hello'; }));
		$router->filter('foo', function($route, $request, $bar, $baz) { return $bar.$baz; });
		$this->assertEquals('barbaz', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', array('before' => 'foo:bar,baz|bar:boom', function() { return 'hello'; }));
		$router->filter('foo', function($route, $request, $bar, $baz) { return null; });
		$router->filter('bar', function($route, $request, $boom) { return $boom; });
		$this->assertEquals('boom', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
	}


	public function testGlobalAfterFilters()
	{
		unset($_SERVER['__filter.after']);
		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->after(function() { $_SERVER['__filter.after'] = true; return 'foo!'; });

		$this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
		$this->assertTrue($_SERVER['__filter.after']);
	}


	public function testBasicAfterFilters()
	{
		unset($_SERVER['__filter.after']);
		$router = $this->getRouter();
		$router->get('foo/bar', array('after' => 'foo', function() { return 'hello'; }));
		$router->filter('foo', function() { $_SERVER['__filter.after'] = true; return 'foo!'; });

		$this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
		$this->assertTrue($_SERVER['__filter.after']);
	}


	public function testPatternBasedFilters()
	{
		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->filter('foo', function($route, $request, $bar) { return 'foo'.$bar; });
		$router->when('foo/*', 'foo:bar');
		$this->assertEquals('foobar', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->filter('foo', function($route, $request, $bar) { return 'foo'.$bar; });
		$router->when('bar/*', 'foo:bar');
		$this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->filter('foo', function($route, $request, $bar) { return 'foo'.$bar; });
		$router->when('foo/*', 'foo:bar', array('post'));
		$this->assertEquals('hello', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->filter('foo', function($route, $request, $bar) { return 'foo'.$bar; });
		$router->when('foo/*', 'foo:bar', array('get'));
		$this->assertEquals('foobar', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());

		$router = $this->getRouter();
		$router->get('foo/bar', function() { return 'hello'; });
		$router->filter('foo', function($route, $request) {});
		$router->filter('bar', function($route, $request) { return 'bar'; });
		$router->when('foo/*', 'foo|bar', array('get'));
		$this->assertEquals('bar', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
	}


	public function testMatchesMethodAgainstRequests()
	{
		/**
		 * Basic
		 */
		$request = Request::create('foo/bar', 'GET');
		$route = new Route('GET', 'foo/{bar}', function() {});
		$this->assertTrue($route->matches($request));

		$request = Request::create('foo/bar', 'GET');
		$route = new Route('GET', 'foo', function() {});
		$this->assertFalse($route->matches($request));

		/**
		 * Method checks
		 */
		$request = Request::create('foo/bar', 'GET');
		$route = new Route('GET', 'foo/{bar}', function() {});
		$this->assertTrue($route->matches($request));

		$request = Request::create('foo/bar', 'POST');
		$route = new Route('GET', 'foo', function() {});
		$this->assertFalse($route->matches($request));

		/**
		 * Domain checks
		 */
		$request = Request::create('http://something.foo.com/foo/bar', 'GET');
		$route = new Route('GET', 'foo/{bar}', array('domain' => '{foo}.foo.com', function() {}));
		$this->assertTrue($route->matches($request));

		$request = Request::create('http://something.bar.com/foo/bar', 'GET');
		$route = new Route('GET', 'foo/{bar}', array('domain' => '{foo}.foo.com', function() {}));
		$this->assertFalse($route->matches($request));

		/**
		 * HTTPS checks
		 */
		$request = Request::create('https://foo.com/foo/bar', 'GET');
		$route = new Route('GET', 'foo/{bar}', array('https', function() {}));
		$this->assertTrue($route->matches($request));

		$request = Request::create('http://foo.com/foo/bar', 'GET');
		$route = new Route('GET', 'foo/{bar}', array('https', function() {}));
		$this->assertFalse($route->matches($request));
	}


	public function testWherePatternsProperlyFilter()
	{
		$request = Request::create('foo/123', 'GET');
		$route = new Route('GET', 'foo/{bar}', function() {});
		$route->where('bar', '[0-9]+');
		$this->assertTrue($route->matches($request));

		$request = Request::create('foo/123abc', 'GET');
		$route = new Route('GET', 'foo/{bar}', function() {});
		$route->where('bar', '[0-9]+');
		$this->assertFalse($route->matches($request));

		/**
		 * Optional
		 */
		$request = Request::create('foo/123', 'GET');
		$route = new Route('GET', 'foo/{bar?}', function() {});
		$route->where('bar', '[0-9]+');
		$this->assertTrue($route->matches($request));

		$request = Request::create('foo/123', 'GET');
		$route = new Route('GET', 'foo/{bar?}/{baz?}', function() {});
		$route->where('bar', '[0-9]+');
		$this->assertTrue($route->matches($request));

		$request = Request::create('foo/123/foo', 'GET');
		$route = new Route('GET', 'foo/{bar?}/{baz?}', function() {});
		$route->where('bar', '[0-9]+');
		$this->assertTrue($route->matches($request));

		$request = Request::create('foo/123abc', 'GET');
		$route = new Route('GET', 'foo/{bar?}', function() {});
		$route->where('bar', '[0-9]+');
		$this->assertFalse($route->matches($request));
	}


	public function testRouteBinding()
	{
		$router = $this->getRouter();
		$router->get('foo/{bar}', function($name) { return $name; });
		$router->bind('bar', function($value) { return strtoupper($value); });
		$this->assertEquals('TAYLOR', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());
	}


	public function testModelBinding()
	{
		$router = $this->getRouter();
		$router->get('foo/{bar}', function($name) { return $name; });
		$router->model('bar', 'RouteModelBindingStub');
		$this->assertEquals('TAYLOR', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());
	}


	/**
	 * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
	 */
	public function testModelBindingWithNullReturn()
	{
		$router = $this->getRouter();
		$router->get('foo/{bar}', function($name) { return $name; });
		$router->model('bar', 'RouteModelBindingNullStub');
		$router->dispatch(Request::create('foo/taylor', 'GET'))->getContent();
	}


	public function testModelBindingWithCustomNullReturn()
	{
		$router = $this->getRouter();
		$router->get('foo/{bar}', function($name) { return $name; });
		$router->model('bar', 'RouteModelBindingNullStub', function() { return 'missing'; });
		$this->assertEquals('missing', $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent());
	}


	public function testRouteCompilationAgainstHosts()
	{
		$this->assertEquals(1, preg_match(Route::compileString('{foo}.website.{baz}'), 'baz.website.com'));
	}


	public function testRouteCompilationAgainstUris()
	{
		$this->assertEquals(1, preg_match(Route::compileString('{foo}'), 'foo'));
		$this->assertEquals(1, preg_match(Route::compileString('foo/{bar}'), 'foo/bar'));
		$this->assertEquals(1, preg_match(Route::compileString('foo/{bar}/baz/{boom}'), 'foo/bar/baz/boom'));
		$this->assertEquals(1, preg_match(Route::compileString('foo/{bar}/{baz}'), 'foo/bar/baz'));

		$this->assertEquals(0, preg_match(Route::compileString('{foo}'), 'foo/bar'));
		$this->assertEquals(0, preg_match(Route::compileString('foo/{bar}'), 'foo/'));
		$this->assertEquals(0, preg_match(Route::compileString('foo/{bar}/baz/{boom}'), 'foo/baz/boom'));
		$this->assertEquals(0, preg_match(Route::compileString('foo/{bar}/{baz}'), 'foo/bar/baz/brick'));

		$this->assertEquals(1, preg_match(Route::compileString('foo/{baz?}'), 'foo/bar'));
		$this->assertEquals(1, preg_match(Route::compileString('foo/{bar}/{baz?}'), 'foo/bar'));
		$this->assertEquals(1, preg_match(Route::compileString('foo/{bar}/{baz?}'), 'foo/bar/baz'));

		$this->assertEquals(0, preg_match(Route::compileString('foo/{baz?}'), 'foo/bar/baz'));
		$this->assertEquals(0, preg_match(Route::compileString('foo/{bar}/{baz?}'), 'foo'));
		$this->assertEquals(0, preg_match(Route::compileString('foo/{bar}/{baz?}'), 'foo/bar/baz/boom'));
	}


	public function testGroupMerging()
	{
		$old = array('prefix' => 'foo/bar/');
		$this->assertEquals(array('prefix' => 'foo/bar/baz'), Router::mergeGroup(array('prefix' => 'baz'), $old));

		$old = array('domain' => 'foo');
		$this->assertEquals(array('domain' => 'baz', 'prefix' => null), Router::mergeGroup(array('domain' => 'baz'), $old));
	}


	public function testRouteGrouping()
	{
		/**
		 * Inhereting Filters
		 */
		$router = $this->getRouter();
		$router->group(array('before' => 'foo'), function() use ($router)
		{
			$router->get('foo/bar', function() { return 'hello'; });
		});
		$router->filter('foo', function() { return 'foo!'; });
		$this->assertEquals('foo!', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());


		/**
		 * Merging Filters
		 */
		$router = $this->getRouter();
		$router->group(array('before' => 'foo'), function() use ($router)
		{
			$router->get('foo/bar', array('before' => 'bar', function() { return 'hello'; }));
		});
		$router->filter('foo', function() {});
		$router->filter('bar', function() { return 'foo!'; });
		$this->assertEquals('foo!', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());


		/**
		 * Merging Filters
		 */
		$router = $this->getRouter();
		$router->group(array('before' => 'foo|bar'), function() use ($router)
		{
			$router->get('foo/bar', array('before' => 'baz', function() { return 'hello'; }));
		});
		$router->filter('foo', function() {});
		$router->filter('bar', function() {});
		$router->filter('baz', function() { return 'foo!'; });
		$this->assertEquals('foo!', $router->dispatch(Request::create('foo/bar', 'GET'))->getContent());
	}


	public function testResourceRouting()
	{
		$router = $this->getRouter();
		$router->resource('foo', 'FooController');
		$routes = $router->getRoutes();
		$this->assertEquals(8, count($routes));

		$router = $this->getRouter();
		$router->resource('foo', 'FooController', array('only' => array('show', 'destroy')));
		$routes = $router->getRoutes();

		$this->assertEquals(2, count($routes));

		$router = $this->getRouter();
		$router->resource('foo', 'FooController', array('except' => array('show', 'destroy')));
		$routes = $router->getRoutes();

		$this->assertEquals(6, count($routes));
	}


	public function testResourceRouteNaming()
	{
		$router = $this->getRouter();
		$router->resource('foo', 'FooController');

		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.index'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.show'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.create'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.store'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.edit'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.update'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.destroy'));

		$router = $this->getRouter();
		$router->resource('foo.bar', 'FooController');

		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.index'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.show'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.create'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.store'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.edit'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.update'));
		$this->assertTrue($router->getRoutes()->hasNamedRoute('foo.bar.destroy'));
	}


	protected function getRouter()
	{
		return new Router(new Illuminate\Events\Dispatcher);
	}

}


class RouteTestControllerDispatchStub extends Illuminate\Routing\Controller {
	public function foo()
	{
		return 'bar';
	}
}


class RouteModelBindingStub {
	public function find($value) { return strtoupper($value); }
}

class RouteModelBindingNullStub {
	public function find($value) {}
}