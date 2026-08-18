function esc(value) {
	return String(value)
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;");
}

function isSerializedPhp(value) {
	if (value === null || typeof value !== "string") return false;
	return /^(N;|b:[01];|i:\d+;|d:[\d.eE+-]+;|s:\d+:"[^"]*";|a:\d+:\{)/.test(value);
}

function phpUnserialize(str) {
	var pos = 0;
	function skipSemicolon() {
		if (str[pos] === ";") pos++;
	}
	function parseValue() {
		if (pos >= str.length) throw new Error("Unexpected end");
		var type = str[pos];
		if (type === "N") {
			pos++;
			if (str[pos] !== ";") throw new Error("Expected ; after N");
			pos++;
			return null;
		}
		if (type === "b") {
			pos += 2;
			var boolChar = str[pos];
			pos++;
			if (str[pos] !== ";") throw new Error("Expected ; after b value");
			pos++;
			if (boolChar === "1") return true;
			if (boolChar === "0") return false;
			throw new Error("Invalid boolean value: " + boolChar);
		}
		if (type === "i") {
			pos += 2;
			var numEnd = pos;
			while (numEnd < str.length && str[numEnd] !== ";") numEnd++;
			var numStr = str.slice(pos, numEnd);
			pos = numEnd + 1;
			var num = parseInt(numStr, 10);
			if (isNaN(num)) throw new Error("Invalid integer: " + numStr);
			return num;
		}
		if (type === "d") {
			pos += 2;
			var numEnd = pos;
			while (numEnd < str.length && str[numEnd] !== ";") numEnd++;
			var numStr2 = str.slice(pos, numEnd);
			pos = numEnd + 1;
			var d = parseFloat(numStr2);
			if (isNaN(d)) throw new Error("Invalid double: " + numStr2);
			return d;
		}
		if (type === "s") {
			pos += 2;
			var lenEnd = pos;
			while (lenEnd < str.length && str[lenEnd] !== ":") lenEnd++;
			var lenStr = str.slice(pos, lenEnd);
			var byteLen = parseInt(lenStr, 10);
			if (isNaN(byteLen) || byteLen < 0) throw new Error("Invalid string length: " + lenStr);
			pos = lenEnd + 1;
			if (str[pos] !== '"') throw new Error("Expected \" at start of string");
			pos++;
			var quoteEnd = pos;
			while (quoteEnd < str.length && str[quoteEnd] !== '"') quoteEnd++;
			var rawContent = str.slice(pos, quoteEnd);
			var encoder = new TextEncoder();
			var contentUtf8 = encoder.encode(rawContent);
			if (contentUtf8.length < byteLen) throw new Error("String content shorter than declared length");
			var stringBytes = contentUtf8.slice(0, byteLen);
			var decoder = new TextDecoder();
			var result = decoder.decode(stringBytes);
			pos = quoteEnd + 1;
			if (str[pos] !== ";") throw new Error("Expected ; after string");
			pos++;
			return result;
		}
		if (type === "a") {
			pos += 2;
			var countEnd = pos;
			while (countEnd < str.length && str[countEnd] !== ":") countEnd++;
			var countStr = str.slice(pos, countEnd);
			var count = parseInt(countStr, 10);
			if (isNaN(count) || count < 0) throw new Error("Invalid array count: " + countStr);
			pos = countEnd + 1;
			if (str[pos] !== "{") throw new Error("Expected { after array count");
			pos++;
			var obj = {};
			for (var i = 0; i < count; i++) {
				var key = parseValue();
				skipSemicolon();
				var val = parseValue();
				skipSemicolon();
				obj[key] = val;
			}
			if (str[pos] !== "}") throw new Error("Expected } after array");
			pos++;
			return obj;
		}
		if (type === "O") {
			throw new Error("Objects (O:) are not supported");
		}
		throw new Error("Unknown type: " + type);
	}
	var result = parseValue();
	if (pos !== str.length) throw new Error("Trailing garbage after serialized value");
	return result;
}

console.log("Testing phpUnserialize...\n");

function test(name, fn) {
	try {
		fn();
		console.log("PASS: " + name);
	} catch (e) {
		console.log("FAIL: " + name + " - " + e.message);
		process.exitCode = 1;
	}
}

function assertStrictEqual(actual, expected, msg) {
	if (actual !== expected) {
		throw new Error((msg || "assertion") + " - expected " + JSON.stringify(expected) + " but got " + JSON.stringify(actual));
	}
}

test("isSerializedPhp: N;", function() {
	assertStrictEqual(isSerializedPhp("N;"), true);
});
test("isSerializedPhp: b:1;", function() {
	assertStrictEqual(isSerializedPhp("b:1;"), true);
});
test("isSerializedPhp: i:42;", function() {
	assertStrictEqual(isSerializedPhp("i:42;"), true);
});
test("isSerializedPhp: d:3.14;", function() {
	assertStrictEqual(isSerializedPhp("d:3.14;"), true);
});
test("isSerializedPhp: s:5:\"hello\";", function() {
	assertStrictEqual(isSerializedPhp('s:5:"hello";'), true);
});
test("isSerializedPhp: a:1:{i:0;i:1;}", function() {
	assertStrictEqual(isSerializedPhp("a:1:{i:0;i:1;}"), true);
});
test("isSerializedPhp: plain string returns false", function() {
	assertStrictEqual(isSerializedPhp("hello world"), false);
});
test("isSerializedPhp: null returns false", function() {
	assertStrictEqual(isSerializedPhp(null), false);
});
test("isSerializedPhp: number returns false", function() {
	assertStrictEqual(isSerializedPhp(42), false);
});

test("phpUnserialize: N; returns null", function() {
	var result = phpUnserialize("N;");
	assertStrictEqual(result, null);
});

test("phpUnserialize: b:0; returns false", function() {
	var result = phpUnserialize("b:0;");
	assertStrictEqual(result, false);
});

test("phpUnserialize: b:1; returns true", function() {
	var result = phpUnserialize("b:1;");
	assertStrictEqual(result, true);
});

test("phpUnserialize: i:0; returns 0", function() {
	var result = phpUnserialize("i:0;");
	assertStrictEqual(result, 0);
});

test("phpUnserialize: i:-42; returns -42", function() {
	var result = phpUnserialize("i:-42;");
	assertStrictEqual(result, -42);
});

test("phpUnserialize: i:1234567890; large integer", function() {
	var result = phpUnserialize("i:1234567890;");
	assertStrictEqual(result, 1234567890);
});

test("phpUnserialize: d:0; returns 0.0", function() {
	var result = phpUnserialize("d:0;");
	assertStrictEqual(result, 0);
});

test("phpUnserialize: d:3.14; returns 3.14", function() {
	var result = phpUnserialize("d:3.14;");
	assertStrictEqual(result, 3.14);
});

test("phpUnserialize: d:-2.5; returns -2.5", function() {
	var result = phpUnserialize("d:-2.5;");
	assertStrictEqual(result, -2.5);
});

test("phpUnserialize: d:1e10; scientific notation", function() {
	var result = phpUnserialize("d:1e10;");
	assertStrictEqual(result, 1e10);
});

test("phpUnserialize: s:0:\"\"; empty string", function() {
	var result = phpUnserialize('s:0:"";');
	assertStrictEqual(result, "");
});

test("phpUnserialize: s:5:\"hello\"; string", function() {
	var result = phpUnserialize('s:5:"hello";');
	assertStrictEqual(result, "hello");
});

test("phpUnserialize: s:3:\"<b>\"; HTML chars are preserved in raw value", function() {
	var result = phpUnserialize('s:3:"<b>";');
	assertStrictEqual(result, "<b>");
});

test("phpUnserialize: s:3:\"<b>\"; esc() escapes HTML", function() {
	var result = phpUnserialize('s:3:"<b>";');
	var htmlEscaped = esc(result);
	assertStrictEqual(htmlEscaped, "&lt;b&gt;");
});

test("phpUnserialize: a:0:{} empty array", function() {
	var result = phpUnserialize("a:0:{}");
	assertStrictEqual(Array.isArray(result), false);
	assertStrictEqual(typeof result, "object");
	assertStrictEqual(Object.keys(result).length, 0);
});

test("phpUnserialize: a:1:{i:0;i:1;} simple array", function() {
	var result = phpUnserialize("a:1:{i:0;i:1;}");
	assertStrictEqual(Object.keys(result).length, 1);
	assertStrictEqual(result[0], 1);
});

test("phpUnserialize: a:2:{i:0;d:3.14;i:1;s:1:\"x\";} array with double followed by more elements (issue repro)", function() {
	var result = phpUnserialize("a:2:{i:0;d:3.14;i:1;s:1:\"x\";}");
	assertStrictEqual(Object.keys(result).length, 2);
	assertStrictEqual(result[0], 3.14);
	assertStrictEqual(result[1], "x");
});

test("phpUnserialize: active_plugins sample (array of plugin paths)", function() {
	var serialized = 'a:2:{i:0;s:27:"woocommerce/woocommerce.php";i:1;s:19:"akismet/akismet.php";}';
	var result = phpUnserialize(serialized);
	assertStrictEqual(Object.keys(result).length, 2);
	assertStrictEqual(result[0], "woocommerce/woocommerce.php");
	assertStrictEqual(result[1], "akismet/akismet.php");
});

test("phpUnserialize: nested array", function() {
	var result = phpUnserialize("a:1:{i:0;a:2:{i:0;i:1;i:1;i:2;}}");
	assertStrictEqual(Object.keys(result).length, 1);
	assertStrictEqual(Object.keys(result[0]).length, 2);
	assertStrictEqual(result[0][0], 1);
	assertStrictEqual(result[0][1], 2);
});

test("phpUnserialize: throws on O: (objects not supported)", function() {
	var threw = false;
	try { phpUnserialize("O:8:\"stdClass\":0:{}"); } catch (e) { threw = true; }
	assertStrictEqual(threw, true);
});

test("phpUnserialize: throws on malformed input", function() {
	var threw = false;
	try { phpUnserialize("a:1:{i:0"); } catch (e) { threw = true; }
	assertStrictEqual(threw, true);
});

test("phpUnserialize: throws on trailing garbage", function() {
	var threw = false;
	try { phpUnserialize("i:42;garbage"); } catch (e) { threw = true; }
	assertStrictEqual(threw, true);
});

test("phpUnserialize: throws on unknown type", function() {
	var threw = false;
	try { phpUnserialize("x:0;"); } catch (e) { threw = true; }
	assertStrictEqual(threw, true);
});

test("phpUnserialize: s:12:\"文学作品\"; unicode string (12 bytes in UTF-8)", function() {
	var result = phpUnserialize('s:12:"文学作品";');
	assertStrictEqual(result, "文学作品");
});

console.log("\nAll tests completed.");